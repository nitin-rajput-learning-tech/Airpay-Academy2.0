# BizLMS import mapping (2026-09-29)

**Status:** Proposed. Companion to `docs/adr/ADR-032-bizlms-data-import.md`. Import decided by Nitin
2026-09-29.
**Scope:** every table of production's 22 BizLMS plugins, plus the BizLMS-shaped data on core tables
(tags, course columns, events, files, enrolments) that the import touches or must leave alone.
**Sources:** twelve feature maps, each checked by a verifier, plus a completeness critique. Where the
verifier corrected the mapper, the corrected rule is written into the tables below, and each feature
lists what was corrected.

**2026-10-07 update.** The owner questions still open after the 2026-09-30 signing were decided under Nitin's
delegation of 2026-10-07 ("self review and decide recommended option"). `docs/cutover/bizlms-import-decisions.json`
carries them (status `accepted`, why starts `[delegated 2026-10-07]`), and
`docs/cutover/OWNER-DECISIONS-2026-10-07.md` lists all 84 with where each is implemented. Every correction made to
this document because of one is marked `2026-10-07 decision <id>`; `F-<nn>` is a follow-up item in the annex of that
document. Where a section below still lists an open question, a marked line answers it.

Path roots used in every citation:

- **BZ** = `D:/Claude Local/Moodle Backup/01-production-codebase/html/` (production 4.1.2 snapshot)
- **SE** = `D:/Claude Local/airpay-ld-os/moodle-enhancement/`
- **TOP** = `D:/Claude Local/airpay-ld-os/`

Within one feature section, a bare file name refers to the file cited in full earlier in that section.

Nothing here was executed. Low-CPU mode applied: files were read, not run.

---

## 0. Rules that apply to every feature

These rules are not repeated in the feature sections.

| # | Rule |
|---|---|
| R1 | Writes go through the ADR-032 writer only. Never call a manager API (`session_manager`, `path_manager`, `program_manager`, `request_manager`, `cart_manager`, `evaluation_manager`, `recompletion_engine`, `skills_manager`, `rating_manager`, `delivery_log::log`, notifier classes). They stamp `time()`, fire events, enrol or send messages. |
| R2 | Target `time*` columns come from the source. Fallbacks are named per column. `time()` only where a target has no source timestamp and the section says "import time". |
| R3 | Tenant roots are validated with `tenant::assert_valid()` (`SE local/sentientia_platform/classes/tenant.php:191-199`), which delegates to `tenant_registry`. Never test `VALID_TENANTS` (`tenant.php:32`) directly. Prefix matches use `tenant::path_descendant_filter()` (`tenant.php:444-466`). |
| R4 | `reset_sequence()` on PRESERVE targets runs in `finalise()`, after commit. Never inside a transaction. |
| R5 | Every schema or code change lands in both trees (`TOP local/<plugin>` and `SE local/<plugin>`), in `install.xml` and an `upgrade.php` step with a version bump, with no raw `<` in a COMMENT attribute, and with en and hi strings. |
| R6 | The per-feature `legacy_id`, `legacy_ref`, `legacy_source` (as a key), `legacykey`, `legacyid` columns, UNIQUE legacy indexes and the `local_sentientia_classroom_legacy` / skills `import_map` tables proposed by the maps are **not built**. The ADR-032 map (`local_sentientia_legacymap`, key `sourcetable, sourceid, subkey`) is the idempotence key. "Key: map" below means that. |
| R7 | A legacy value is copied into Sentientia only if a Sentientia reader shows it, an engine uses it, or a code fix in this document adds a reader for it. Everything else stays in the legacy table, which is the archive. So `legacy_json`, `legacy_meta`, `audience_json` and the classroom `sourcedata` archive proposed by the maps are dropped. |
| R8 | Unknown enum values found in preflight block the feature until the decisions file maps them. The importer never guesses a status. |
| R9 | Every new column that names a person is declared in its plugin's privacy provider (metadata, export, erase or anonymise), with en and hi strings. New actor-column names are added to `USER_COLUMNS` (`SE local/sentientia_platform/tests/privacy_coverage_test.php:52-63`). 2026-10-07 decision F-86: `usercreated`, `usermodified`, `modified_by` and `trainerid` are added in ONE change after the program merge, in both trees, together with the provider declarations the guard then asks for (emails, talent, the ten config-table plugins, the users sync tables, classroom, programs; actor columns anonymised to 0 on erasure). It does not block Stage B. |
| R10 | Reports carry ids and codes only. |
| R11 | Deleted users' rows are imported as history unless the section says otherwise. Readers named in the section filter `u.deleted = 0` or show a badge. |
| R12 | A derived group (a row built from many source rows) is keyed by a non-personal integer: the group's own integer key (a cart identifier) or `MIN(id)` of the group. Never a user id. |
| R13 | The importer never deletes a row anywhere, and never writes a legacy table. Where a map proposed deleting a duplicate, the duplicate is recorded as `merged` or `folded` and left in place (2026-10-07 decision CRS-08: `folded` when the survivor is a native core row or a row of another step, as in course_tags). |
| R14 | Readers that fall back to a legacy table have that fallback removed in the release that ships the feature's importer. |
| R15 | Id policy per step: **P** = PRESERVE (legacy id kept, collision blocks, adopt rule of ADR-032), **M** = MAP (new id, resolved through the map). |

Outcome words: `imported`, `adopted`, `merged` (a duplicate folded into the row that won), `folded`
(became part of another target row), `archived` (kept only in the legacy table, deliberately),
`skipped` (could not be imported; reason recorded).

---

## 1. Inventory: every source table and its owner

95 table names are declared or created by the 22 plugins. Each has exactly one owning feature.
"Declined" means the owner lists it in `declined_tables()` with the reason shown; its rows stay in the
database and are fingerprinted by parity.

| Source table | Feature | Outcome |
|---|---|---|
| local_costcenter | org | import (P) |
| local_costcenter_permissions | org_roles | import if rows |
| local_org_dept_roles | org_roles | import if rows |
| local_coursedetails | course_lookups | fold into `course.open_*` if rows |
| local_moduleconfig | course_lookups | declined: configuration |
| local_filters | course_lookups | declined: configuration of BizLMS pages |
| local_certificate | certificates (no map yet) | unclaimed until mapped; parity exit 2 |
| local_groups | cohort_scope | import |
| local_custom_category | course_lookups | import (P) |
| local_course_types | course_lookups | import (P) |
| local_dashboardcourses | course_lookups | import |
| local_tags | course_tags | declined: tenant overlay with corrupt values |
| local_tag_mapping | course_tags | declined: no writer |
| local_logs, local_courseerrors | legacy_logs | import |
| local_location_institutes, local_location_room | classroom | import |
| local_classroom, local_classroom_sessions | classroom | import (P) |
| local_classroom_courses, _trainers, _users, _attendance, _waitlist | classroom | import |
| local_classroom_trainerfb, _completion | classroom | archived |
| local_classroom_test_score | classroom | archived if empty; blocker if rows |
| local_classroom_categories (may not exist) | classroom | archived if present |
| local_program | program | import (P) |
| local_program_levels, _level_courses, _users | program | import |
| local_bc_level_completions | program | import (completed rows) |
| local_bcl_cmplt_criteria, local_bc_completion_criteria | program | fold |
| local_program_completions_bk, local_bc_level_comp_bk, local_program_test_score | program | archived if empty; blocker if rows |
| local_program_trainers, local_program_trainerfb | program | import if rows (conditional tables) |
| local_learningplan | learningplan | import (P) |
| local_learningplan_courses, local_learningplan_user | learningplan | import |
| local_plan_course_status | learningplan | import if rows |
| local_learningplan_approval | request | import (fold into requests) |
| local_evaluations | evaluation | import (P) |
| local_evaluation_item, _completed, _users, _template | evaluation | import |
| local_evaluation_value | evaluation | fold into responses |
| local_eval_completedtmp, local_eval_valuetmp | evaluation | declined: drafts |
| local_eval_sitecourse_map | evaluation | declined: dead table; blocker if rows |
| local_biz_cart_history, _ledger, _invoices, _credits | cart | import |
| local_biz_cart_id | cart | fold into order numbers |
| local_recompletion_config, _cc, _cc_cc, _cmc, _qa, _qg, _sst, _ltia, _qr and 7 `_qr_*` | recompletion | import (16 tables) |
| local_request_records | request | import |
| local_request_comments | request | fold if rows |
| local_request_config | request | declined: form settings |
| local_skill, local_skill_categories, local_course_levels (P), local_interested_skills | skills | import |
| local_skillmatrix | skills | declined unless Nitin decides otherwise |
| local_userssyncdata, local_syncerrors | users | import |
| local_transcript_history | users | import if rows |
| local_uniquelogins | users | import if decided |
| local_userdata | users | declined: derived mirror; reconciliation report only |
| local_rating, local_comment, local_like | ratings | import |
| local_ratings_likes | ratings | declined: derived cache; parity oracle |
| local_emaillogs | notifications | import |
| local_notification_type, _info, _strings | notifications | declined: configuration, read in place |

Tables that the snapshot's install files do not declare but that evidence says exist, or that code
reads. Preflight runs `table_exists` and counts for each. The unclaimed-table check keeps any that
hold rows visible until an owner exists.

| Table | Evidence | Owner |
|---|---|---|
| local_challenge | unguarded indexes in `BZ local/learningplan/db/upgrade.php:184,272-288`; rendered at `BZ local/learningplan/classes/render/view.php:1537-1539` | none yet: map needed (gap G2) |
| local_positions, local_domains | unguarded indexes in `BZ local/users/db/upgrade.php:67-68,107-117`; `user.open_positionid/open_domainid` point at them (`BZ local/users/db/upgrade.php:23-27`) | users (proposed lookup import) |
| block_request_records, _comments, _config | written by `BZ local/request/admin/comment.php:228`, `deny_course.php:310`, `bulk_deny.php:151` | request |
| local_certification | request `compname='certification'` resolves to it (`BZ local/request/classes/api/requestapi.php:327`) | none yet (gap G3) |
| local_request_formfields, local_request_form_data | only in NEXT/PREVIOUS attributes (`BZ local/request/db/install.xml:26,39`) | request (existence check) |
| local_crequest_* | read by dead code (`BZ local/request/lib/requestlib.php:88-205`) | request (existence check) |
| local_email_logs | dead writers (`BZ local/notifications/lib.php:583-730`) | notifications (import if rows) |
| local_onlinetests | not in production code; assumed by `SE local/sentientia_exams/classes/exam_manager.php:107-118` | exams (blocker if present with rows) |
| paygw_airpay, paygw_course_enrolmentlog, paygw_airpay_errorlog | `BZ payment/gateway/airpay/db/install.xml:7-58` | cart reads them as evidence; declined as a table (payment-gateway decision) |
| block_trending_modules | fed by `BZ local/ratings/update.php:63-71` | ratings (declined: derived) |

---

## 2. Features, owners and run order

| Feature | Owner plugin (target) | Depends on | Atomic | Notes |
|---|---|---|---|---|
| org | sentientia_org | none | yes | everything resolves org paths through it |
| org_roles | sentientia_roles | org | yes | core `role_assignments` is a declared core write |
| cohort_scope | sentientia_org | org | yes | |
| course_lookups | sentientia_courses | org | yes | `course.open_*` backfill is a declared core write |
| course_tags | sentientia_courses | none | yes | `tag_instance` update is a declared core write |
| enrolments | sentientia_courses | none | yes | gap G6; core writes `enrol` and `user_enrolments` (2026-10-07 decision F-35: this row was missing) |
| legacy_logs | sentientia_core (target `local_sentientia_admin_log`) | org | no | 2026-10-07 decision F-74: the registry refuses a `local_sentientia_legacy*` name and requires `org` for tenant resolution (`tenant_resolution_needs_org`) |
| exams | sentientia_exams | org | yes | derived from core course rows |
| users | sentientia_users | org | no | |
| notifications | sentientia_emails | none | no | largest local table (13 072 rows) |
| recompletion | sentientia_recompletion | none | no | `sst` may be very large |
| cart | sentientia_cart | none | no | |
| skills | sentientia_skills | recompletion | no | history may also read the recompletion archive (decision) |
| classroom | sentientia_classroom | org | yes | |
| program | sentientia_programs | org | yes | |
| learningplan | sentientia_learningpath | org, skills | yes | |
| evaluation | sentientia_evaluation | org, classroom, program | yes | a form and its questions, responses and assignments land together |
| request | sentientia_request | classroom, program, learningplan | no | also owns `local_learningplan_approval` |
| ratings | sentientia_ratings | classroom, program, learningplan | no | |

"Atomic" = the importer returns `atomic() = true`; the runner uses one outer transaction when the
preflight total is under the threshold (ADR-032, Transactions).

Run order (`--all` sorts it): org; org_roles, cohort_scope, course_lookups, course_tags, enrolments,
legacy_logs, exams, users, notifications, recompletion, cart; skills; classroom, program; learningplan;
evaluation, request, ratings (2026-10-07 decision F-35: `enrolments` added).

---

## 3. org

**Owner:** `local_sentientia_org`. **Depends:** none. **Atomic:** yes.

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_costcenter (`BZ local/costcenter/db/install.xml:7-41`) | local_sentientia_org (`SE local/sentientia_org/db/install.xml:5-69`) | P | map; target id = legacy id |
| `{files}` component `local_costcenter`, filearea `costcenter_logo`, category contexts (`BZ local/costcenter/lib.php:719-722`) | `{files}` component `local_sentientia_org`, filearea `org_logo`, system context, same itemid | n/a | pathnamehash; skip when it exists |

`local_costcenter` also **stays in place**. Readers use it directly: category link by path
(`SE local/sentientia_org/classes/accesslib.php:533-545,607-618`), `org_legacy_source`
(`SE local/sentientia_core/classes/org_legacy_source.php:65-100`). It must never be dropped or
uninstalled.

### Column map

```
id                  -> id                      preserve (BZ install.xml:9; SE install.xml:7-8)
fullname            -> fullname                COALESCE ''; source nullable CHAR225, target NOT NULL CHAR254 (BZ :10; SE :9)
shortname           -> shortname               text.fit to 100 + warning; children are built parent_child (BZ lib.php:694-696); target CHAR100 (SE :11)
parentid            -> parentid                NULL -> 0 (BZ :12; SE :15)
description         -> description             (BZ :13; SE :13)
visible             -> visible                 same meaning (BZ :14; SE :21)
path                -> path                    verbatim '/1/5/12', built at BZ lib.php:701-706 (BZ :18; SE :17)
depth               -> depth                   COALESCE(depth, number of path segments); source nullable INT(20) (BZ :19), target NOT NULL DEFAULT 1 (SE :19)
sortorder (vancode) -> sortorder INT           rank siblings by the source string, write rank*10. Source is '01', '01.01', ... (BZ lib.php:111-136); target INT (SE :51)
costcenter_logo     -> org_logo                itemid kept; the file is copied (see files row) (BZ :26; SE :23)
brand_color, button_color, hover_color -> same fit to 20; source CHAR50 (BZ :30-32), target CHAR20 (SE :25-30)
theme               -> theme_scheme            fit to 50; source CHAR255 (BZ :22), target CHAR50 (SE :31)
timecreated         -> timecreated             source value
timemodified        -> timemodified            source value (data_migration.php:84 overwrote it with now)
category            -> not copied              read in place (accesslib.php:540,611)
multipleorg, childpermission, shell, usermodified -> not copied (open question)
favicon, footer_text, email_*, support_email, help_url, hero_*, custom_css -> NULL (SE :33-50)
```

### Idempotence, order, tenant, status

- **Key:** map. A row already at the legacy id with no map row, written by `data_migration.php`
  (`SE local/sentientia_org/data_migration.php:69,86`), is adopted when id, shortname and path match
  (adopt signature `shortname, path`; `data_migration.php` copies both verbatim at :71,:74). The adopted
  row is rewritten with the corrected map above.
- **Order:** org rows ordered by depth then id (parents first); then the logo copy in `finalise()`;
  then `reset_sequence`; then purge the org caches.
  2026-10-07 decision IDN-04: the logo copy is a reviewed side effect (copy-only, insert-only, idempotent, the
  originals are never touched). The org importer declares its source and target file areas through the
  `copies_files` marker instead of a `core_writes` entry; `--purge-feature` leaves the copy; the run report counts it
  (`files_copied:local_sentientia_org/org_logo=N`). 2026-10-07 decision F-21: file content is required on the target,
  so Stage B restores `filedir` together with the database. April: 14 organisations reference a logo itemid but only 5
  legacy logo file rows exist, so expect a `logo_file_missing` warning for the itemids that have no file row.
- **Tenant rule:** `path` copied verbatim. Its root (first segment) must pass `tenant::assert_valid`.
  Rows with an empty or NULL path are skipped (`not_org_row`): `BZ local/costcenter/costcentersettings.php:67-72`
  can insert a row that carries only `multipleorg`.
  2026-10-07 decision IDN-03: `org:invalid_tenant_root` (a root other than 1, 77 or 177) and `org:unmapped_enum`
  (a `visible` value other than 0 or 1) are needs-owner reasons and are NOT pre-accepted; both stay fail-closed.
  April: 213 organisations with roots 1 (206 rows), 77 (2) and 177 (5), no empty path and `visible` 1 on every row,
  so neither fires. A hit at Stage B stops the run: Nitin decides (a new tenant or a re-parent), then the accepted
  reason or enums entry is added with its count, a re-approval event.
- **Status mapping:** `visible` 1 = shown, 0 = hidden, on both sides; `org_manager::get_children`
  filters `visible = 1` (`SE local/sentientia_org/classes/org_manager.php:131-132`).

### Side effects to avoid

- Do not run `SE local/sentientia_org/cli/migrate_all.php`. It copies `local/costcenter:*` role
  capabilities to `local/sentientia_org:*` (`migrate_all.php:141-191`), which re-grants what ADR-031
  decision 7 revokes. It copied ten BizLMS capabilities (the five `local/costcenter:*`,
  `local/courses:manage` and `:enrol`, `local/classroom:manageclassroom`, `local/users:edit` and
  `:bulkstatuschange`) with role, context and permission unchanged, and it never revoked anything.
  Reading it as a copy of the ARCHETYPE grants was right for six of the targets and wrong for the rest:
  - On production the grants sit on manager-archetype roles (core manager and the tenant-admin role 9).
    Six targets (`sentientia_org:view`, `sentientia_courses:manage` and `:enrol`,
    `sentientia_classroom:manage`, `sentientia_users:edit` and `:bulkstatuschange`) have a manager
    archetype, so the plugin install grants them already. A copy adds nothing.
  - Four have none on purpose (`sentientia_org:manage_multiorganizations`, `:manage`,
    `:manage_ownorganization`, `:manage_owndepartments`). A copy would give role 9 cross-tenant
    organisation delete, edit and visibility through `admin.php`, `delete_org`, `toggle_visibility` and
    `edit_org`.
  - `local/classroom:manageclassroom` has no archetype in BizLMS, so it exists only as overrides, and the
    local copy cannot show which (0 legacy `role_capabilities` rows: BizLMS was uninstalled there). If the
    trainer role held it, trainers lose classroom management with the BizLMS code.
  **Replacement:** `SE local/sentientia_platform/cli/repair_bizlms_capabilities.php` (ADR-032
  "Capabilities"), first step of the cutover slice. It lists the grants on capabilities of plugins missing
  from disk per role and context, applies only an allow-list Nitin signs line by line, never grants
  `sentientia_org:manage`, `sentientia_org:manage_multiorganizations` or `sentientia_platform:crosstenant`,
  and never revokes. `crosstenant` goes by hand to the named platform role. If the Stage B inventory shows
  only archetype-default grants on roles 1 and 9, the allow-list is empty.
  2026-10-07 decision IDN-05: the platform role is created by `tools/uat/adr031_crosstenant_role.php` (`--dry-run`,
  then `--apply`; migration plan step 4f-f) at Stage B and at cutover, with the one capability
  `local/sentientia_platform:crosstenant` and NO members. Members are added by hand at
  `/admin/roles/assign.php?contextid=1` only when Nitin names them, so the site admins stay the only cross-tenant
  callers until then (signed key `org.crosstenant_platform_role`).
- Do not flip `org_legacy` or enable `org_dualwrite`; Gate C keeps legacy ON
  (`SE local/sentientia_core/classes/org.php:44-58`).
- `SE local/sentientia_org/classes/task/sync_cohorts.php:51-101` adds cohort members with events; it
  must not run in the window (cron off).

### Schema additions

None required at cutover. Optional, for Enterprise N: `local_sentientia_org.categoryid` so that
`accesslib.php:533-545,607-618` stop reading `local_costcenter.category`.

### Code fixes

1. `SE local/sentientia_org/data_migration.php`: refuse and point to the new CLI. The importer replaces
   it. Its defects: skip-if-populated (:47-53), non-existent `theme_scheme` source (:81), raw vancode
   into INT (:82), `timemodified` = now (:84), `reset_sequence` before commit (:93,:96).
2. `SE local/sentientia_org/cli/migrate_all.php`: refuse; mark not-for-cutover. (Done in Phase 0; its header
   now says what it copied and points to the capability repair. The earlier note that it was tied to the
   retired `local_airpay_` names was wrong.)
2b. `SE local/sentientia_org/classes/accesslib.php:315-394` (`legacy_cap()` and its callers in
   `can_manage_multi`, `can_manage`, `is_org_head`, `is_dept_head`, `can_manage_classroom`): remove the
   BizLMS fallbacks **in the org importer's release**, like the reader fallbacks. ADR-032 keeps the BizLMS
   plugins installed, so their `capabilities` rows survive in the restored database and role 9 passes
   `can_manage_multi()` through `local/costcenter:manage_multiorganizations` (an ADR-031 hole in the
   navigation, used by `theme/sentientia`'s `core_renderer`). Not removed earlier: today they are what
   keeps tenant admins working on a restored UAT database.
3. `SE local/sentientia_core/classes/org_legacy_source.php:75,97`: query `local_costcenter.fullname`,
   not `.name`. As written, every backfilled unit is named "Unit <id>"
   (`SE local/sentientia_core/classes/org_reconciler.php:119-121`). Needed only before any
   `org_legacy` flip; `backfill_org.php` is not part of cutover.
4. Branding: after the logo copy, `SE local/sentientia_org/classes/branding_manager.php:207-210` must
   build URLs for component `local_sentientia_org`, filearea `org_logo`, served by
   `SE local/sentientia_org/lib.php:54-60`. The current `local_costcenter` URL is served by nothing on
   5.2 (`TOP lib/filelib.php:5370-5373` returns not found without the plugin's lib.php).
5. `SE local/sentientia_org/cli/disable_bizlms.php:63-65`: it calls `local_forum`, `local_groups` and
   `local_tags` "Not used"; their data is live. Documentation fix.

### Fixture

Legacy `local_costcenter` from the BZ install.xml: rows `/1`, `/77`, `/177`, `/1/5`, `/1/5/12` with
sortorders `'01'`, `'02'`, `'03'`, `'01.01'`, `'01.01.01'`, category ids, a logo file in a category
context; one junk row with only `multipleorg` and a 120-character shortname; one row pre-written at its
id by a `data_migration`-style copy. Assert: ids kept; INT sortorder ranks; `theme` in `theme_scheme`;
junk row skipped; long shortname truncated with a warning; pre-written row adopted; logo served from
`local_sentientia_org/org_logo`; second run inserts nothing.

### Verification corrections applied

- Logos are copied into the system context under `local_sentientia_org/org_logo`; "leave them under
  `local_costcenter`" would not serve them.
- `theme` gets a 255 -> 50 length guard; `depth` gets COALESCE with the segment count.
- Sort order: `'01.01'` into an INT is coerced to 1 under strict mode (lossy, no abort); depth-3 codes
  such as `'01.01.01'` abort. The rank transform is required either way.
- `org_manager::get_descendants` is `/`-bounded (`org_manager.php:164,176`), not unbounded.

### Open questions

- What did `multipleorg` (`BZ local/costcenter/costcentersettings.php:57-73`), `childpermission` and
  `shell` mean on live, and must they be preserved? Answered 2026-09-30: not copied, they stay in `local_costcenter`
  (signed `org.unmapped_columns = not_copied`).

---

## 4. org_roles

**Owner:** `local_sentientia_roles`. **Depends:** org. **Atomic:** yes.
**Core write:** `role_assignments` insert, plus `local_sentientia_roles_auditlog`.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_costcenter_permissions (`BZ local/costcenter/db/install.xml:43-62`) | core `role_assignments` at `context_coursecat(local_costcenter.category)` + one audit row (`SE local/sentientia_roles/db/install.xml:5-48`) | map, subkey `pos:<n>` per exploded user (n = the 1-based position in the comma list, never the user id: legacymap.subkey holds no personal data, and outcome::insert() refuses a `user:` subkey); target skipped when `(roleid, contextid, userid, component='', itemid=0)` exists (outcome `folded`, reason `already_assigned`; 2026-10-07 decision F-13: the code folds, it does not merge) |
| local_org_dept_roles (`BZ local/assignroles/db/install.xml:6-21`) | same | same |

Both tables are expected to be empty. Current BizLMS code only deletes from
`local_costcenter_permissions` (`BZ local/costcenter/classes/external.php:209`), and
`local_org_dept_roles` is referenced only by its install and upgrade files (the savepoint even names a
non-existent plugin, `BZ local/assignroles/db/upgrade.php:59`). Real org roles are already core
`role_assignments` at the category context (`BZ local/assignroles/classes/external.php:52-55`), carried
by the restore.

### Column map

```
local_costcenter_permissions:
  userid (CHAR225, may be a comma list) -> role_assignments.userid  explode, int, skip deleted users (BZ :46)
  costcenterid -> contextid   context_coursecat of local_costcenter.category (BZ :47)
  roleid -> roleid            roleid 0 -> skip 'no_role' (NOT NULL DEFAULT 0, BZ :48)
  value -> filter             import value = 1 only, pending confirmation (BZ :49)
  timecreated -> timemodified; usermodified -> modifierid (BZ :50-52)
local_org_dept_roles:
  departmentid > 0 ? departmentid : costcenterid -> contextid via local_costcenter.category (BZ :9-10)
  userid, roleid -> same
  user_modified > 0 ? user_modified : user_created -> modifierid (BZ :14, :13)
  timemodified > 0 ? timemodified : timecreated -> timemodified (BZ :15-16)
audit row: action 'role_assigned', roleshortname snapshot, contextid, targetuserid, changedby = modifier,
  open_path = the actor's user.open_path, timecreated = the assignment time (SE roles install.xml:10-32)
```

### Tenant, status, side effects

- **Tenant:** implicit through the category context. The audit row's `open_path` is the actor's, because
  the audit list is scoped by actor or target path (`SE local/sentientia_roles/classes/role_manager.php:546-575`).
- **Status:** `value` 1 = assigned (assumed). No other state.
- **Users outside or without a tenant (2026-10-07 decisions IDN-01 and F-13):** a user outside the organisation's
  tenant is left out of the row (warning `user_outside_org_tenant`). A user with no tenant path is left out too
  (warning `user_without_tenant`), because ADR-031 decisions 4 and 6 say no tenant means nothing: a scoped
  `roles:assign` may only assign to users in the actor's own tenant, so the native UI could never make that grant,
  and BizLMS never read these tables. A row with nobody left is skipped with the needs-owner reason
  `user_outside_org_tenant` (checked first) or `user_without_tenant`; a role without the coursecat level is skipped
  `role_not_assignable`. None of the three is pre-accepted (IDN-02): the owner accepts each with its Stage B count.
  Signed key `org_roles.user_without_tenant = skip_fail_closed`. April: both tables are empty and the only pathless
  live user is a site admin, who already holds every capability at every category, so the change alters nothing today.
- **Side effects:** insert directly; never `role_assign()` (it fires `role_assigned`, as
  `BZ local/assignroles/classes/local/assignrole.php:39-45` does). Mark contexts dirty in `finalise()`.

### Code fixes

- `SE local/sentientia_roles/classes/role_manager.php:366-412`: list assignments at the coursecat
  contexts of org-linked categories too, scoped by the same tenant rule. Today it lists only the system
  context (:370,:381), so every BizLMS org role is invisible in the Sentientia UI.

### Fixture

Legacy permissions with a comma-listed userid, a roleid 0 row, a deleted user, a user with no tenant path (skipped
`user_without_tenant`, 2026-10-07 decision IDN-01), a value 0 row; one `local_org_dept_roles` row. Assert role assignments at the right category context, audit rows, no
`role_assigned` event, second run no-op.

### Verification corrections applied

- roleid 0 is skipped and reported. `user_modified` and `timemodified` of `local_org_dept_roles` are mapped.

### Open questions

- What does `local_costcenter_permissions.value = 1` mean, and is `userid` really a comma list? 2026-10-07 decision
  F-13: answered by data, not by meaning: the April production copy has 0 rows in `local_costcenter_permissions` and
  in `local_org_dept_roles`, and all 11 restored category-level role assignments are same-tenant (roles 9 and 10).
  Re-check both counts at Stage B.

---

## 5. cohort_scope

**Owner:** `local_sentientia_org`. **Depends:** org. **Atomic:** yes.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_groups (`BZ local/groups/db/install.xml:7-25`; `open_path` from `BZ local/groups/db/upgrade.php:34-42`) | NEW local_sentientia_cohort_scope | map; target UNIQUE cohortid |
| `{files}` component `local_groups` (edit path) or `groups` (add path), filearea `description`, itemid cohortid (`BZ local/groups/edit.php:129-130,161-165,172-173`) | `{files}` component `cohort`, filearea `description` (copy; originals kept) | pathnamehash |

Core `cohort` and `cohort_members` carry unchanged. `local_groups` is the tenant satellite of a cohort
(`BZ local/groups/lib.php:245-254`). No Sentientia code reads it; `sync_cohorts` manages only its own
`ap_org_*` cohorts (`SE local/sentientia_org/classes/task/sync_cohorts.php:51-75`).

### Column map

```
cohortid     -> cohortid       join on cohortid ONLY (BZ :10)
open_path    -> open_path      normalise; '0' (the NOT NULL DEFAULT, BZ :13) and '' mean unknown -> derive below
departmentid (comma list CHAR100, BZ lib.php:253) -> departmentids
costcenterid -> fallback only  (not set on create, BZ lib.php:248-254)
usermodified, timemodified -> same (BZ :14-15)
```

### Tenant, status, side effects

- **Tenant:** normalised `open_path` validated against `local_sentientia_org.path`. If unknown: derive
  from `cohort.contextid` (coursecat -> `local_costcenter.category` -> path). A system-context cohort
  gets `''` (cross-tenant only) and is reported.
- **Data-quality trap:** `local_groups_update_groups()` updates `local_groups` using the cohort id as the
  row id (`BZ local/groups/lib.php:292-293`), so edits overwrote whichever row has `id = cohortid`.
  Cross-check `open_path` against the cohort context and report mismatches.
- **Side effects:** no cohort API calls (`BZ local/groups/lib.php:256-261,294-298,374-380` fire events).
  2026-10-07 decision IDN-04: the description file copy goes through the `copies_files` marker (copy-only, idempotent,
  originals untouched, left in place by `--purge-feature`). 2026-10-07 decision F-21: `finalise()` throws
  `description_files_not_copied` when a description file row has no content on the target, so Stage B restores
  `filedir` with the database (April: 0 description files; the one `local_groups` row has a valid `/77` path on a
  system-context cohort and is reported as a mismatch).

### Schema additions

`local_sentientia_cohort_scope`: id, cohortid INT UNIQUE, open_path CHAR255, departmentids CHAR255,
usermodified, timemodified. (The map's `legacyid` column is dropped, R6.) `usermodified` is an actor
column: declare it in the org provider.

### Fixture

A cohort, a member, a `local_groups` row whose id differs from its cohortid, a row with `open_path '0'`
in a category context, description files under both component names. Assert scope rows, derived paths,
file copies, no cohort events.

### Verification corrections applied

- `'0'` is treated as empty and the path is derived from `cohort.contextid`.

### Open questions

None beyond the ADR.

---

## 6. course_lookups

**Owner:** `local_sentientia_courses` (catalog readers in `local_sentientia_catalog`). **Depends:** org.
**Atomic:** yes. **Core write:** `course.open_*` backfill with `set_field`.

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_course_types (`BZ local/courses/db/install.xml:40-58`; recreated at `BZ local/courses/db/upgrade.php:131-148`) | NEW local_sentientia_course_type | P | map |
| local_custom_category (`BZ local/custom_category/db/install.xml:6-26`) | NEW local_sentientia_course_category | P | map |
| local_dashboardcourses (`BZ local/courses/db/install.xml:59-67`) | local_sentientia_featured_courses (`SE local/sentientia_courses/db/install.xml:5-28`) | M | map, subkey `course:<id>` per exploded course |
| local_coursedetails (`BZ local/costcenter/db/install.xml:63-92`) | core `course.open_*` (`SE local/sentientia_core/classes/substrate.php:91-112`) | n/a | map; only NULL or 0 target columns are written |
| local_moduleconfig, local_filters | declined | | configuration (`BZ local/costcenter/db/install.xml:93-122`); count reported |

### Column maps

```
local_course_types -> local_sentientia_course_type
  id -> id (preserve; course.open_identifiedas keeps pointing at it)             (BZ :42)
  name -> name; shortname -> shortname (spaces stripped at write, BZ local/courses/classes/external.php:1618) (BZ :43-44)
  orgid -> tenant_path   0 -> NULL (all tenants, BZ local/courses/coursestypes.php:70-71); N -> local_costcenter.path of N;
                         an unresolved org -> NULL (BZ :45). 2026-10-07 decision F-35: tenant_path is NULLABLE and NULL = no tenant
  active -> active       1 enabled, 0 disabled (toggle BZ external.php:1733-1736)    (BZ :46)
  id IN (1..5) -> protected = 1   (no edit/delete, BZ coursestypes.php:59-63)
  timecreated, timemodified, usercreated, usermodified -> same                     (BZ :47-50)

local_custom_category -> local_sentientia_course_category
  id -> id (preserve; course.open_categoryid)                                      (BZ :8)
  fullname, shortname -> same                                                      (BZ :9-10)
  parentid -> parentid   0 = top (BZ local/custom_category/classes/lib.php:14)     (BZ :11)
  costcenterid -> tenant_path   root of local_costcenter.path of costcenterid; NULL -> NULL (set from the form or
                                USER.open_path, BZ local/custom_category/classes/lib.php:10-11) (BZ :12)
  path -> path   category-tree path '/<parent>/<id>', not an org path (classes/lib.php:37-42) (BZ :17)
  depth -> depth (BZ :18); timecreated, timemodified, usercreated, usermodified -> same (BZ :13-16)

local_dashboardcourses -> local_sentientia_featured_courses
  courseids of EVERY row (union, explode ',', de-duplicate) -> one row per courseid; skip ids with no course
     (the form inserts whenever id <= 0, BZ local/courses/classes/form/adddashboardcourse_form.php:132-136;
      the reader FIND_IN_SETs across all rows, BZ local/courses/renderer.php:499-504)
  costcenterid -> the course's tenant, homed by the course's open_path at write time; there is NO finalise() re-home
     call (2026-10-07 decision F-35; the rule is that of SE local/sentientia_courses/db/upgradelib.php:75-131)
  sort_order -> rank by course.id DESC x10 (reproduces renderer.php:504; step size as featured_manager.php:145,278)
  label -> NULL; timecreated -> import time (no source timestamp)

local_coursedetails -> course.open_* (set_field, only where NULL or 0)
  cost -> open_cost; coursecompletiondays -> open_coursecompletiondays; coursecreator -> open_coursecreator;
  identifiedas -> open_identifiedas; requestcourseid -> open_requestcourseid; skill -> open_skill
     (BZ :69,:72,:73,:75,:76,:82; SE substrate.php:96-102)
  proficiencylevel -> open_level; credits (CHAR) -> open_points  candidates, verify on data (BZ :81,:68):
     LEFT in the legacy table and counted in preflight (2026-10-07 decisions CRS-06 and CRS-15,
     `course_lookups.coursedetails_candidate_columns = leave`)
  costcenterid -> ignored (course.open_path is authoritative)
  enrollstartdate, enrollenddate, duration, prerequisite_courses -> stay in the legacy table (BZ :70-71,:74,:83)
```

### Tenant, status, side effects

- **Tenant:** course types and categories get `tenant_path` as above (NULL = no tenant, 2026-10-07 decision F-35),
  validated with `tenant::assert_valid`.
  Featured rows are re-homed by the course's `open_path` (ADR-031 follow-up rule, `upgradelib.php:56-71`).
- **Status:** `active` as above. Featured widget shows only `course.visible = 1` and hides enrolled
  courses (`SE local/sentientia_courses/classes/featured_manager.php:216-229`).
- **Side effects:** `set_field`, never `update_course()` (no `course_updated`). `prerequisite_courses`
  must never become `course_completion_criteria`. Purge `local_sentientia_catalog` caches in `finalise()`.

### Schema additions

- `local_sentientia_course_type`: id (preserved), name CHAR255, shortname CHAR255, tenant_path CHAR255
  NULL (NULL = no tenant; 2026-10-07 decision F-35 corrected NOT NULL DEFAULT ''), active INT1 DEFAULT 1, protected INT1 DEFAULT 0, usercreated, usermodified,
  timecreated, timemodified; indexes on tenant_path and active.
- `local_sentientia_course_category`: id (preserved), fullname, shortname, parentid, path CHAR512,
  depth, tenant_path, usercreated, usermodified, timecreated, timemodified; indexes on parentid and
  tenant_path.
- Declare `usercreated`/`usermodified` in the courses provider.

### Code fixes

1. `SE local/sentientia_catalog/classes/category_manager.php:23`: read `local_sentientia_course_category`
   with no legacy fallback; add a `tenant_path` filter to `get_root_categories` and `get_children`
   (:100-124 have none).
2. `SE local/sentientia_catalog/classes/catalog_manager.php:476-477`: label types from
   `local_sentientia_course_type` via the **exploded** `open_identifiedas` list. It is a comma list
   (`BZ local/myteam/classes/output/courseallocation_lib.php:168`); never join on equality.
3. `SE local/sentientia_courses/classes/course_fields.php:37-38`: remove `open_costcenterid` and
   `open_departmentid`; neither is a substrate course column (`substrate.php:91-112`).

### Fixture

Types 1-7 (orgid 0 and 77, active 0 and 1), categories for two tenants (one with NULL costcenterid),
two `local_dashboardcourses` rows `'12,15,999'` and `'15,20'`, a coursedetails row. Assert preserved
ids, `tenant_path`, featured union de-duplicated and re-homed, missing course skipped, backfill only
into empty columns, no `course_updated` event.

### Verification corrections applied

- `local_custom_category` tenant: resolve through `local_costcenter.path` and take the root; NULL -> NULL (2026-10-07 decision F-35).
  (The map's column map and tenant rule disagreed.)
- `local_dashboardcourses` is not one row: union all rows and de-duplicate.

### Open questions

- `local_coursedetails` columns with no home (enrolment dates, duration, prerequisites): add columns, or
  leave them in the legacy table (default)?
- Featured courses: global as in BizLMS, or re-homed per tenant (the default above)?
- Confirm `local_moduleconfig` and `local_filters` stay in place. Confirm `local_certificate` belongs to
  the certificates gap map.

### Decisions of 2026-10-07

- **CRS-06 and CRS-15:** `local_coursedetails.proficiencylevel -> open_level` and `credits -> open_points` are LEFT in
  the legacy table and counted in preflight (signed `course_lookups.coursedetails_candidate_columns = leave`, recorded
  so the report shows an owner decision and not a silent default). April: 0 `local_coursedetails` rows, `open_level`
  already set on 394 of 411 courses, `open_points` empty everywhere with no Sentientia reader (R7). If Stage B reports
  `coursedetails_candidate_open_level_not_written` above 0, `fill_level_only` (never `open_points`) is added as a
  re-approval event before the hash is pinned.
- **CRS-07:** `course_lookups:tenant_unresolved` (a featured list whose course names an unregistered tenant root, or a
  lookup row skipped under `skip`) is not pre-accepted; Nitin accepts it after Stage B only if its count is above 0.
  April: 0. 21 of the 82 custom categories name cost centre 80, which no longer exists in `local_costcenter`; they
  import pathless (cross-tenant callers only) under the signed `tenant.unresolved.course_lookups = pathless` and are
  counted, not skipped. Course types have `orgid` 0 (6 rows) and 1 (1 row). The single featured list names 6 courses,
  all rooted `/77`. One forum pseudo-course (course 16) carries `/80` and is already kept out of the catalog.
- **F-38 (before cutover, with F-86):** `course.open_coursecreator` (a core substrate user id; 0 courses on April) is
  declared in the `sentientia_core` provider and anonymised per `users.erasure_treatment`; the courses provider
  docblock lists the `local_sentientia_courses_enrolmove` ledger (ids and timestamps only, no person, not declared).
- **Open questions above, answered:** enrolment dates, duration and prerequisites stay in the legacy table
  (`course_lookups.coursedetails_unhomed_columns`); featured courses are re-homed per tenant
  (`course_lookups.featured_scope`); `local_moduleconfig` and `local_filters` stay in place and `local_certificate`
  belongs to the certificates gap map (`course_lookups.declined_config_tables`).

---

## 7. course_tags

**Owner:** `local_sentientia_courses`. **Depends:** none. **Atomic:** yes.
**Core write:** `tag_instance` update in place.

### Source and target

| Source | Target | Key |
|---|---|---|
| core `tag_instance` WHERE component = `local_courses` AND itemtype = `courses` (`BZ local/courses/db/tag.php:28-35`) | the same row, component `core`, itemtype `course` | map, sourcetable `tag_instance`, sourceid = tag_instance.id |
| local_tags, local_tag_mapping | declined | see below |

Why: once `local_courses` is gone, its tag area is orphaned, and uninstalling it deletes every instance
(`BZ tag/classes/area.php:386-400`, called from `BZ lib/adminlib.php:186`).

### Map

```
component 'local_courses' -> 'core'; itemtype 'courses' -> 'course'
itemid, contextid, tagid, ordering, tiuserid -> unchanged
duplicate: a core/course instance already exists for (itemid, contextid, tiuserid, tagid)
   -> outcome folded, reason duplicate_core_instance, target = the surviving core row; the legacy row is left
      untouched (R13). 2026-10-07 decision CRS-08: `folded`, not `merged`, because the survivor is a native core row
      and `outcome::merge` needs a winner that is a source row of the same step
   instance whose course is gone -> skipped course_missing; whose tag is gone -> skipped tag_missing
      (neither needs the owner; both added by CRS-08)
```

- **Preflight:** compare `tag_area.tagcollid` and `enabled` for `local_courses/courses` with
  `core/course`. If the collections differ, stop: remapped instances would point at tags in the wrong
  collection.
- **Accounting:** this step changes its own source filter, so it is an in-place core step with a derived unit
  `#tag_instance.id` and the trail table `local_sentientia_courses_tagmove`. The importer's own `verify()` proves the
  accounting, and the filter afterwards matches exactly the `folded` and `skipped` rows, not 0 (2026-10-07 decision
  CRS-08). A framework in-place step kind is deferred to after Stage B.
- `local_tags` is declined: its tenant columns are corrupt by construction (create passes a string path
  into an INT slot, `BZ local/courses/classes/external.php:179`; update passes the department as
  costcenter, :237; signature `BZ local/tags/classes/tag.php:752`). `local_tags.taginstanceid` still
  points at the same ids after an in-place update. `local_tag_mapping` has no writer (only
  `BZ local/tags/db/upgrade.php:34`).
- **Side effects:** direct SQL update, never `core_tag_tag` APIs (they fire `tag_added`).
- Tag areas of classroom, learning plan and evaluation (`BZ local/classroom/db/tag.php:28-35`,
  `BZ local/learningplan/db/tag.php:28-35`, `BZ local/evaluation/db/tag.php:30-31`) are counted in
  preflight and left alone: Sentientia has no tag area for those items (gap G5).

### Fixture

Instances under `local_courses/courses`, one already duplicated as `core/course`, a `local_tags` row.
Assert remap, the duplicate recorded as folded (`duplicate_core_instance`) and untouched, no tag events, second run no-op.

### Verification corrections applied

- Collection preflight added. The duplicate is no longer deleted (R13).

### Cutover notes (2026-10-07 decision CRS-09)

The two notes that lived only in the courses state card are runbook rules now (`MIGRATION-REHEARSAL-RUNBOOK.md`,
section "BizLMS import: Stage B checks", and `docs/operations/cutover-day-runbook.md`):

- BizLMS courses tagged with the lifecycle `mandatory` tag become joiner auto-enrol triggers once moved. Recommended
  future flip, recorded and not decided: `sentientia.lifecycle.autoenrol.enabled` stays OFF until `course_lookups` and
  `course_tags` are complete and L&D has reviewed the `will_move_with_the_lifecycle_mandatory_tag` list. The flag is
  OFF by default and tenant-scoped, but a course with an empty `open_path` counts as platform-wide.
- BizLMS tag tenancy (`local_tags`) is not carried over (declined above); moved tags are ordinary core tags.
- Stage B check: if the preflight count `will_move` is above 0, open the core tag index as a `/77` learner and confirm
  that no `/1` course names are listed. If they are, handle it under the ADR-031 course-listing rules before cutover
  (a core tag index that lists course names across tenants is more visible than BizLMS, owner rule 3).
- April: 0 `local_courses/courses` tag instances (`tag_instance` has 4 rows, none in that area), so course_tags is a
  no-op on April and its tests are the only evidence of the move.

---

## 8. legacy_logs

**Owner:** `local_sentientia_core`. **Depends:** org (2026-10-07 decision F-74). **Atomic:** no.

| Source | Target | Key |
|---|---|---|
| local_logs (`BZ local/courses/db/install.xml:7-25`) | NEW local_sentientia_admin_log (2026-10-07 decision F-74: the registry refuses a `local_sentientia_legacy*` name) | map |
| local_courseerrors (`BZ local/courses/db/install.xml:26-39`) | same | map |

`local_logs` is BizLMS's admin audit trail, written by `local_custom_logs()`
(`BZ local/courses/classes/action/insert.php:38-55`) on course insert, update and delete
(`BZ local/courses/edit.php:139,168`; `BZ local/courses/courses.php:87`). `local_courseerrors` is the bulk
upload error log (`BZ local/courses/upload/processor.php:287-303`).

```
local_logs:
  'local_logs' -> source; event (insert|update|delete) -> event (BZ :10); module -> module (BZ :11)
  description -> description verbatim; it contains the actor's first name (BZ local/courses/courses.php:82-85) (BZ :12)
  type -> itemref (the course id) (BZ :13); usercreated -> userid (BZ :16); usermodified -> usermodified (BZ :17)
  actor's user.open_path at import -> actor_path; timecreated, timemodified -> same (BZ :14-15)
local_courseerrors:
  'local_courseerrors' -> source; 'upload_error' -> event; 'course' -> module
  reason (CHAR225) -> description (BZ :29); userid -> userid (BZ :30); time -> timecreated and timemodified (BZ :31)
```

- **Tenant:** `actor_path`; a scoped reader sees rows inside its tenant via `path_descendant_filter`,
  mirroring the roles audit log (`SE local/sentientia_roles/classes/role_manager.php:546-575`). A deleted
  actor keeps the row.
- **Schema:** `local_sentientia_admin_log`: id, source CHAR40, event CHAR225, module CHAR225, description
  TEXT, itemref CHAR225, userid INT, usermodified INT, actor_path CHAR255, timecreated, timemodified;
  indexes on userid, timecreated, source. (The map's `sourceid` and UNIQUE key are dropped, R6.)
  Declare `userid`, `usermodified` and `description` in the core provider.
- **Code fix:** a read-only admin report page (`admin_log.php`), behind the default-OFF flag
  `sentientia.legacy_logs.report.enabled` plus the capability `local/sentientia_core:viewadminlog`, with no archetype
  grant (2026-10-07 decision F-74).
- **Fixture:** insert/update/delete log rows and error rows, one with a deleted actor; assert scoping as
  a tenant admin.
- **Corrections applied:** `usermodified` added; `reason` is CHAR225.
- **Open question:** retention and DPDP erasure treatment for the actor first names in `description`. Answered
  2026-09-30 by the signed keys `legacy_logs.retention = keep_no_purge` and `legacy_logs.description_erasure =
  keep_row_scrub_name`. 2026-10-07 decision F-74: erasure recognises only the English BizLMS description shapes; any
  other shape is replaced whole. Other BizLMS plugins may also call `local_custom_logs()`; every row is imported
  regardless of module.
- **Decisions of 2026-10-07:**
  - COMMS-C2 (recommended flip, Nitin's call after the evidence): `sentientia.legacy_logs.report.enabled` STAYS OFF.
    BizLMS had no reader of `local_logs` or `local_courseerrors`, only writers (`BZ local/courses/classes/action/insert.php:54`,
    `BZ local/courses/upload/processor.php:302`), and both tables hold 0 rows on April (both exist there, so the
    required-source concern does not block).
  - F-75 (before the report flag is ever flipped): render a NULL or 0 time as a dash, not 1970 in the When column, and
    correct the `itemref` COMMENT: it says 'course id', but forum and exam rows carry their own ids.
  - F-76 (Stage B): `admin_log.php` keeps pagelayout `standard`, because `admin_externalpage_setup` cannot run while the
    page is registered only with the flag ON; revisit after screenshots. The top-level `settings.php` calls
    `admin_log::report_enabled()` at admin-tree build, so include that call in the Stage B performance pass. Stage B
    counts decide whether the report is ever worth enabling.

---

## 9. exams

**Owner:** `local_sentientia_exams`. **Depends:** org. **Atomic:** yes.

### Source and target

A BizLMS online exam is a **course** with `open_module = 'online_exams'` and `open_coursetype = 1`
(`BZ local/onlineexams/classes/external.php:150,161`), format `singleactivity` (:152), holding a quiz.
Its completion is `course_modules_completion.completionstate > 0`
(`BZ local/onlineexams/classes/local/general_lib.php:105`). A Sentientia exam wraps one quiz
(`SE local/sentientia_exams/db/install.xml:9-10`). Attempts, grades and completions carry in core and are
read by quiz id (`SE local/sentientia_exams/view.php:68-95`).

| Source | Target | Key |
|---|---|---|
| `#course.online_exams` (derived: course rows with the markers above; the physical source table is `quiz`, 2026-10-07 decision F-35) | local_sentientia_exams (`SE local/sentientia_exams/db/install.xml:5-34`) | map, sourceid = course id; subkey `''` for the lowest quiz id, `quiz:<id>` for any further quiz (reported) |
| forum pseudo-courses (`open_module = 'forum'`, `BZ local/forum/classes/external.php:147,158`) | none: they stay ordinary courses | declined |
| local_onlinetests | none | preflight: if the table exists with rows, blocker |

`idx_quizid` is not unique (`SE install.xml:32`) and the duplicate check exists only in
`exam_manager::create` (`exam_manager.php:286`), so the importer checks quiz id itself.

### Column map

```
quiz.id -> quizid
course.fullname -> name        ('fullname — quiz.name' when the course has more than one quiz)
course.open_path -> open_path  verbatim
deepest org id in open_path -> costcenterid   (org id, the same convention as create(), exam_manager.php:318-321)
second path segment -> departmentid
course.category -> categoryid  (course_categories.id, SE install.xml:14-15)
quiz.timelimit -> duration     seconds; 0 -> NULL (exam_manager.php:305)
grade_items.gradepass / grademax * 100 (itemmodule quiz) -> passinggrade   percent; 0 -> NULL (view.php:85 defaults to 50)
course.visible -> status and visible   1 -> 1/1, 0 -> 0/0 (SE install.xml:20-22)
course.timecreated, course.timemodified -> timecreated, timemodified
```

### Tenant, side effects

- **Tenant:** `course.open_path`. Empty -> NULL (2026-10-07 decision F-35), visible to cross-tenant callers only
  (`exam_manager.php:43-53,151-160`); reported.
- **Side effects:** `exam_reminder` and `exam_overdue` message learners and supervisors for active exams
  in the reminder window (`SE local/sentientia_exams/classes/task/exam_reminder.php:52,107-110`;
  `exam_overdue.php:34,53-91`). Both are off by default (`SE local/sentientia_exams/settings.php:22-25,59-62`).
  The load step `exams.reminder_seed` (2026-10-07 decision F-35: a load step, not `finalise()`) pre-seeds
  `local_sentientia_exams_remind_sent` for every (enrolled user, imported exam,
  bucket, timeclose) with timeclose before cutover, so enabling them later cannot flood supervisors.

### Code fixes

1. `SE local/sentientia_exams/view.php:93`: the pass percentage divides by `SUM(quiz_grades.grade)` over
   all users; use `quiz.sumgrades` (or `quiz_grades.grade / quiz.grade`). 2026-10-07 decision F-40 (before UAT
   sign-off): `view.php` also computes `pass_pct` as distinct passed learners over the attempt count, and `count_failed`
   subtracts learners from attempts (mixed units); use distinct learners with a finished attempt as the denominator
   for both figures, with a test.
2. `SE local/sentientia_exams/classes/exam_manager.php:107-118`: remove the `local_onlinetests` fallback (R14).
3. `SE local/sentientia_catalog/classes/catalog_manager.php:195-209,319-331,360-369,441-449,476-477`:
   exclude or label the pseudo-courses. BizLMS lists normal courses with `open_coursetype = 0 OR NULL`
   (`BZ local/courses/classes/local/general_lib.php:120`); 1 + `online_exams` = Exam, 1 + `forum` = Forum.
   2026-10-07 decision CRS-14: exclude them from the guest storefront (`commerce::get_public_catalog`, both its COUNT
   and its SELECT, through the reusable `ORDINARY_COURSES_ONLY` condition); keep them in the learner's in-progress rail,
   which shows only courses the learner is enrolled in, labelled 'Exam' or 'Forum' from `open_module` (en and hi
   strings; `format_course` says 'E-Learning' for `open_coursetype` 1 today). April: 5 visible exam courses in the
   Public tenant would show on the storefront; none of the 8 exam courses has a fee instance and guest and self
   enrolment are disabled on all of them. Sentientia's exam pages are manager and teacher only, so the enrolled course
   is a learner's only Sentientia path to an assigned exam, which is why the rail keeps it.
4. `TOP theme/airpayux/classes/output/core_renderer.php:1726-1730,1745-1748`: unguarded SQL on
   `local_onlinetests`; fix or remove.

### Fixture

An exam course with two quizzes (timelimit, gradepass, one finished attempt, completionstate 1), a forum
course, a course with NULL open_path. Assert one exam per quiz, percent pass grade, open_path, remind_sent
pre-seeded, no messages from the reminder tasks afterwards.

### Verification corrections applied

- Quiz-id uniqueness enforced by the importer. Multi-quiz courses are flagged, not designed around.

### Open questions

- Show forum pseudo-courses in the catalog as courses, as a "Forums" section, or hide them? Answered 2026-09-30:
  hidden (signed `exams.forum_pseudocourses = exclude_from_catalog`); CRS-14 above applies it to the guest storefront.
- Multi-quiz exam course: one exam per quiz (default) or only the final quiz? Answered 2026-09-30: one exam per quiz
  (signed `exams.multi_quiz = per_quiz`). April: all 8 exam courses have 1 quiz each and registered roots (`/1` 2,
  `/77` 5, `/177` 1), so there is no multi-quiz and no pathless exam; the reminder seed volume is small (3 closed exam
  quizzes, 154 enrolment rows on those courses; read the `exams.reminder_seed` counts in the Stage B report, F-40).

---

## 10. users

**Owner:** `local_sentientia_users` (report readers in `local_sentientia_reports`). **Depends:** org.
**Atomic:** no.

No Sentientia code reads any of these legacy tables, not even as a fallback. The profile shows only
`course_completions` (`SE local/sentientia_users/classes/user_manager.php:284-295,389-401`).

**Blocker before import:** `SE local/sentientia_users/classes/privacy/provider.php:17-18` declares a null
provider, yet `sync_errors` stores email, employee code and names (`SE local/sentientia_users/db/install.xml:54-58`)
and `sync_runs` stores `usercreated` (:27-28). The provider must be real before about 4 800 legacy rows land.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_userssyncdata (`BZ local/users/db/install.xml:21-38`; costcenterid from `BZ local/users/db/upgrade.php:121-129`) | local_sentientia_users_sync_runs (`SE local/sentientia_users/db/install.xml:7-44`) | map |
| local_syncerrors (`BZ local/users/db/install.xml:6-19`) | local_sentientia_users_sync_errors (`SE install.xml:46-78`) + synthetic runs | map (errors); synthetic runs keyed `#local_syncerrors.orphan_day` / `#local_syncerrors.service_day`, sourceid = MIN(error id) of the group (R12) |
| local_transcript_history (`BZ local/users/db/install.xml:39-68`) | NEW local_sentientia_users_transcript | map; only if production has rows |
| local_uniquelogins (`BZ local/users/db/upgrade.php:34-49`) | NEW local_sentientia_users_logindays | map, sourceid = MIN(id) per (userid, count_date); only if decided |
| local_userdata (`BZ local/users/db/install.xml:69-83`) | none | declined: derived mirror of `user.open_path` (`BZ local/users/classes/functions/users.php:126-128,184-201`); reconciliation report only |
| local_positions, local_domains (no install file in the snapshot) | proposed NEW local_sentientia_users_position / _domain, id preserved | gap G4: preflight SHOW COLUMNS; map before cutover |
| `{user}.open_*` columns (`BZ local/users/db/install.php:6-145`) | core `{user}`, unchanged | not history; read directly (`SE user_manager.php:155-178`) |

### Column maps

```
local_userssyncdata -> sync_runs  (one row per HRMS upload, written after processing:
    BZ local/users/classes/cron/syncfunctionality.php:333-344; BZ local/users/classes/cron/cronfunctionality.php:342-352)
  (none) -> filename ''            BizLMS never stored it; sync_runs.php:83 shows '(no file)'
  (none) -> source 'bizlms'        char(20) (SE install.xml:13-14); readers label it (code fix)
  usercreated -> usercreated       = $USER->id (syncfunctionality.php:339) (BZ :29)
  (tenant rule) -> costcenterid
  newuserscount -> insertedcount   NULL -> 0 (BZ :24)
  updateduserscount -> updatedcount NULL -> 0 (BZ :25)
  errorscount -> errorcount        NULL -> 0; copied verbatim, see status notes (BZ :26)
  warningscount + supervisorwarningscount -> warningcount  COALESCE each to 0 (BZ :27-28; SE install.xml:23-24)
  (none) -> skippedcount 0, suspendedcount 0
  (derived) -> totalrows = inserted + updated + attached error rows with severity 'error' (reported as derived)
  (none) -> status 'completed'     a BizLMS row exists only after the loop finished
  (none) -> error_summary NULL     (sync_run_detail.php:57-59 paints any value red)
  timecreated -> timecreated; timemodified -> timemodified (NULL -> timecreated) (BZ :30,:32)

local_syncerrors -> sync_errors
  (matching) -> runid              see "Run matching"
  (none) -> csv_line_number 0      BizLMS never stored the line
  email -> email                   fit 254, keep '-' (writers store '-' when empty, cronfunctionality.php:988-992) (BZ :13)
  idnumber -> employee_code        fit 100, keep '-'; pre-count values over 100 (BZ :14 is CHAR255)
  (none) -> username '-'
  firstname, lastname -> same, only if production has the columns; else '' (insert_record drops unknown fields);
                          for error rows set lastname '' when it equals firstname (cronfunctionality.php:999)
  error -> error_message           verbatim, NULL -> '' (BZ :9); items joined with ',' (cronfunctionality.php:983)
  mandatory_fields -> mandatory_fields (BZ :12)
  type (production-only) -> severity   'Warning' -> 'warning', else 'error' (writers: cronfunctionality.php:1272,
                                        syncfunctionality.php:1141); if the column is absent, infer (status notes)
  sync_file_name (production-only) -> drives run matching: 'Employee' = upload/cron
                                        (syncfunctionality.php:708; cronfunctionality.php:1000), 'Service' = HR web
                                        service (BZ local/users/classes/cron/userservice.php:146)
  modified_by -> modified_by       NULL -> 0 (BZ :11); 2 for the web service (userservice.php:133)
  date_created -> timecreated      NULL -> 0 (BZ :10)

local_transcript_history -> local_sentientia_users_transcript  (no BizLMS PHP writes or reads it;
    lang string 'Transcript History (2015-2016)', BZ local/users/lang/en/local_users.php:418-420)
  userid -> userid                 if > 0 and in {user}; else resolve employee_id to exactly one NON-DELETED user
                                   (u.deleted = 0) by open_employeeid, then idnumber; none or ambiguous -> 0 (BZ :53,:42)
  employee_id -> employee_id; fullname -> learner_name; training_title -> title (NULL -> '(untitled)')
  training_type -> training_type; training_object_id -> objectref; training_location -> location (BZ :43-51)
  courseid -> courseid             if > 0, exists and not SITEID; else 0 (BZ :52)
  completion_date -> completion_date_raw + timecompleted (explicit format list d/m/Y, d-m-Y, Y-m-d, d-M-Y,
                     Excel serial, in Moodle's server timezone; unparseable -> NULL) (BZ :45)
  status -> status_raw + status (normalised, see status notes) (BZ :46)
  transcript_score -> score_raw + score; training_hours -> hours_raw + hours (decimal or H:MM) (BZ :48-49)
  usercreated, usermodified, timecreated, timemodified -> same, NULL -> 0 (BZ :54-57)
  (derived) -> costcenterid = root_for_user(resolved user) or 0; open_path = resolved user's open_path

local_uniquelogins -> local_sentientia_users_logindays  (written on user_loggedin, BZ local/users/classes/observer.php:24-47)
  userid -> userid; count_date -> logindate (midnight of the day, observer.php:30-31,44)
  type -> source ('web', the only value, observer.php:45); timemodified -> timecreated
  day, month, year -> not copied (redundant)
  duplicates per (userid, count_date) collapse; userid 2 got a row on every login (observer.php:36-37)
```

### Run matching (sync errors)

1. Rows with `sync_file_name = 'Service'` are never matched to a run. They go to one synthetic run per
   server-timezone day, `costcenterid` 0.
2. **Errors** (`date_created` = `time()`): attach to the legacy run R of the same uploader
   (`R.usercreated = modified_by`) whose window contains `date_created`. The window is
   (previous run of that uploader, `R.timecreated`], and never earlier than `R.timecreated - 3600`
   (`hrms_async` caps a run at one hour, `BZ local/users/sync/hrms_async.php:30`).
3. **Warnings** (`date_created` = midnight of the upload day): attach to the first run of the same
   uploader on that day.
4. **Leftovers:** one synthetic run per (modified_by, server-timezone day): `source 'bizlms'`,
   `status 'completed'`, inserted and updated 0, error, warning and total counts recomputed from its
   children in a recompute step, `timecreated` = MAX(date_created), `usercreated` = modified_by.
   BizLMS showed a non-admin only the errors they caused (`BZ local/users/lib.php:1275-1280`), so
   grouping by uploader keeps that boundary. 2026-10-07 decision IDN-07: the readers keep it too (run list
   tenant-wide, rejected lines for the uploader and cross-tenant callers only; see the decisions block below).

### Tenant rule

- **sync_runs:** (1) uploader is cross-tenant now (`tenant::is_cross_tenant`, `tenant.php:65-76`) -> 0;
  (2) else `tenant::root_for_user(usercreated)` (`tenant.php:41-47`) if it passes `assert_valid`;
  (3) else 0. The legacy `costcenterid` is **not** used: it is the org of the last row processed
  (`syncfunctionality.php:193-197,343`), 0 after an org error, and never set by `cronfunctionality`
  (`cronfunctionality.php:342-351`). Using it would show one tenant's error PII to another.
- **sync_errors:** inherit the run's tenant (`SE local/sentientia_users/sync_run_detail.php:20-29` checks
  `run.costcenterid`). Synthetic runs use the sync_runs rule on `modified_by`; user 2 -> 0.
- **transcript:** readers scope by the learner's **current** `u.open_path` with `path_filter('u')`
  (`tenant.php:380-404`); rows with userid 0 get costcenterid 0 and open_path NULL, visible only to
  cross-tenant callers (`tenant.php:396-398`).
- **logindays:** no stored tenant; readers join `{user}` and apply `path_filter('u')`.

### Status notes

- Sync runs: all `completed`; the badge expects completed|running|failed (`SE sync_runs.php:75-80`).
  Counters are copied as BizLMS showed them. They are not true totals: `syncfunctionality` counts error
  messages, not rows (increments at 144, 156, 168, 275, 448, 459, 467), and rows that `continue` log no
  error; `cronfunctionality` warnings are the last row's count only (:302), and one more warning row is
  written after the loop from the last row (:311-314), so warning rows will not reconcile with
  `warningscount`. Report the difference; do not de-duplicate.
- Severity inference without the `type` column: `warning` when `date_created` is an exact midnight in
  **Moodle's effective server timezone** (`$CFG->timezone`, else php.ini); else `error`. Warnings take the
  date in the user's timezone and then `strtotime()` it in the PHP default timezone
  (`cronfunctionality.php:1252-1253`; `syncfunctionality.php:1122-1123`), so a user in another timezone
  can produce a server-midnight stamp for a different day. Record it as a known inference.
- Transcript status (values unknown until I-20; the table has no writer): lower(trim(status_raw))
  `completed|complete|passed|pass|attended|yes` -> `completed`; `in progress|inprogress|started|registered|enrolled`
  -> `inprogress`; `failed|fail|not passed` -> `failed`; `not started|pending|assigned` -> `notstarted`;
  `cancelled|withdrawn|no show|absent` -> `cancelled`; anything else -> `unknown`. `status_raw` is always
  kept and shown. The list is an owner decision.

### Side effects to avoid

- Never `hrms_importer::import_csv` (`SE local/sentientia_users/classes/hrms_importer.php:113`),
  `user_create_user` or `user_update_user`; no `user_created`/`user_updated` events; the welcome mailer
  really sends email (`SE local/sentientia_users/tests/welcome_mailer_test.php:18`).
- Do not set `hrms_sync_last_run` / `hrms_sync_last_run_id` (`SE local/sentientia_users/classes/task/hrms_sync.php:83-84`).
- Transcript rows never go into `course_completions`, `logstore_standard_log` or
  `local_sentientia_xapi_stmts` (purged after retention, `SE local/sentientia_xapi/db/install.xml:12`).
- Do not write `{user}.open_path` from `local_userdata`; do not kill sessions.

### Schema additions

- The `legacykey` columns and UNIQUE indexes proposed on `sync_runs` and `sync_errors` are not built (R6).
- NEW `local_sentientia_users_transcript`: id; userid INT NOT NULL DEFAULT 0; employee_id, learner_name,
  title (NOT NULL), training_type, objectref, location CHAR255; courseid INT NOT NULL DEFAULT 0;
  status CHAR20 NOT NULL DEFAULT 'unknown'; status_raw, completion_date_raw, score_raw, hours_raw CHAR255;
  timecompleted INT NULL; score, hours NUMBER(10,2) NULL; costcenterid INT NOT NULL DEFAULT 0;
  open_path CHAR255 NULL; source CHAR20 NOT NULL DEFAULT 'bizlms'; usercreated, usermodified,
  timecreated, timemodified. Indexes (userid, timecompleted), (costcenterid), (courseid). FK userid only
  (courseid 0 means off-platform). Built only if production has rows.
- NEW `local_sentientia_users_logindays`: id; userid; logindate; source CHAR20 DEFAULT 'web';
  timecreated. UNIQUE (userid, logindate), index (logindate). Conditional.

### Code fixes

1. `SE local/sentientia_users/classes/privacy/provider.php:7-11,17-18`: real metadata, userlist and plugin
   provider (export by userid, modified_by, usercreated; erase or anonymise under the DPDP design). Blocker.
2. `SE local/sentientia_users/sync_runs.php:39-46`: paging (hard `LIMIT 100` at :45); label source
   `bizlms` (:86). 2026-10-07 decision XC-IMPORTED-HISTORY-READERS: with the new default-OFF flag
   `sentientia.users.imported_sync_history` OFF the page adds `AND source <> 'bizlms'`, so an imported run is listed
   only when the flag is ON.
3. `SE local/sentientia_users/sync_run_detail.php:86-87`: tie-break `id ASC`, paging past 500; :102 prints
   a dash for line 0. 2026-10-07 decisions IDN-07 and XC-IMPORTED-HISTORY-READERS: after the tenant check the page
   refuses a `bizlms` run when `sentientia.users.imported_sync_history` is OFF, and shows the rejected lines only
   when `sync_access::can_see_lines($run)` is true (cross-tenant caller, or the uploader of the run); otherwise it
   keeps the header and statistics and shows the notice `hrms_lines_uploader_only` (en and hi).
4. `SE local/sentientia_users/lang/en/local_sentientia_users.php:241` and `lang/hi/...:228`: the link
   points to a non-existent `hrms_history.php`; point it at `sync_runs.php` via `moodle_url`.
5. `SE local/sentientia_users/classes/user_manager.php` (after :284-295): `get_transcript_history($userid)`
   with `u.deleted = 0`, rendered as "Earlier training records (imported)" after
   `templates/profile.mustache:354`, not added to completed totals; behind new flag
   `sentientia.users.legacy_transcript` (default OFF) in a new `db/feature_flags.php`.
6. `SE local/sentientia_reports/classes/report_manager.php:25-38,250-256`: report type
   `training_transcript`, scoped by `u.open_path` like :281-285, filtering `u.deleted = 0`; optional
   "active days (90d)" in `run_user_activity` (:410-466) from logindays.

### Fixture

Use `\local_sentientia_org\test\bizlms_fixture` as a trait: `use` it and call `$this->ensure_bizlms_schema()`
(it is a trait with a protected method, `SE local/sentientia_org/classes/test/bizlms_fixture.php:33,39`).
Legacy tables in exact install.xml shape (no firstname, lastname or type columns), plus a variant with the
production-only `type` and `sync_file_name` columns. Users: site admin; tenant A admin UA; tenant B admin
UB; learner LA with open_employeeid E1; two users sharing idnumber DUP, one of them deleted. Runs: R1 by
UA with legacy costcenterid = B (proves it is ignored); R2 by the site admin. Errors: by UA at T-10
(attaches to R1); by UA at server midnight of T's day (warning, R1); by UA at T-7200 (orphan day run); a
'Service' row by user 2 inside R2's window (goes to the service day run, not R2). Transcript rows: userid
NULL + E1 (resolves to LA); DUP (resolves to the non-deleted user); courseid 999999 (0); status
'Completed', date '15/06/2016', score '85%', hours '1:30'; status 'Weird' (unknown). Uniquelogins: two
rows for userid 2 on one day. Assert tenants (R1 = A, R2 = 0), scoping as UA and UB, severity, derived
totals, no course_completions, logstore or xapi rows, empty sinks, `hrms_sync_last_run` unchanged,
second run identical; privacy export and delete for UA and LA.

### Verification corrections applied

- Tenant rule step 3 (fallback to the legacy costcenterid) deleted.
- `sync_file_name` drives matching; 'Service' rows get their own day run.
- Midnight inference and day buckets use Moodle's effective server timezone.
- Fixture uses the trait correctly.
- Transcript resolution and readers use non-deleted users only.
- The extra post-loop warning row is reported, not de-duplicated.
- `sync_file_name` and `type` added to the column map.
- Synthetic run keys use MIN(error id) instead of a user id (R12).

### Open questions

- I-20 counts for the five tables; SHOW COLUMNS of `mdl_local_syncerrors` (firstname, lastname, type,
  sync_file_name); production `date.timezone` and `$CFG->timezone`.
- Transcript: is it Airpay data (the lang string says 2015-2016)? Approve the status normalisation.
  Should rows count toward completion totals? (Proposed: no.)
- Admin-uploaded runs: costcenterid 0 (proposed), or visible to tenant admins?
- `local_uniquelogins`: import, or rely on `logstore_standard_log` (compare the oldest `loggedin` log row
  with MIN(count_date), and check loglifetime)?
- DPDP: anonymise or delete imported transcript and sync-error rows on erasure?
- `local_userdata` reconciliation: if `costcenterpath <> open_path`, which is true at cutover?
- `local_positions` / `local_domains`: approve the lookup import (gap G4).

### Decisions of 2026-10-07

- **IDN-07 and XC-IMPORTED-HISTORY-READERS (HRMS sync history):** BizLMS showed a non-admin only the error lines they
  caused (`BZ local/users/lib.php:1266-1283`, filtered by `modified_by`) and the statistics tenant-wide (`:1326-1340`).
  Sentientia matches that exactly, for imported and native runs alike: the run list stays tenant-wide, and a run's rejected
  lines (e-mail, employee code, name) are shown only to the uploader and to cross-tenant callers. Imported runs (source
  `bizlms`) are also a history reader and sit behind the new default-OFF flag `sentientia.users.imported_sync_history`
  (OFF: `sync_runs.php` lists no `bizlms` run and `sync_run_detail.php` refuses one with a notice; ON: the line rule
  applies). No Airpay user sees more prospective-employee data after cutover than today. April: 749 runs and 4,874 rejected
  lines; 659 runs and 4,414 lines were made by site admins (tenant 0, cross-tenant callers only), 90 runs and 460 lines by
  4 non-admin uploaders. Signed keys `users.sync_history_visibility = runs_tenant_wide_lines_uploader_only` and
  `framework.imported_rows_on_admin_pages`. Recommended future flip (Nitin's call): ON for Airpay with the other readers
  after he reviews the evidence. F-16: capture desktop and mobile as a tenant manager (uploader and non-uploader) and as a
  cross-tenant admin, for the default-ON changes to `sync_runs.php` (paging, 'Imported from BizLMS') and
  `sync_run_detail.php` (paging, dash for line 0) as well.
- **IDN-06 (DPDP, login days):** imported login days (`local_sentientia_users_logindays`) are DELETED on an erasure
  request: a (user, day) row has no meaning once the person is removed, and UNIQUE (userid, logindate) rules out
  anonymising. The transcript and sync-error rows stay anonymised (signed `users.erasure_treatment = anonymise`). The
  import deletes nothing; erasure acts on the Sentientia copy through Moodle's privacy request workflow, where a person
  approves each deletion request. Signed key `users.logindays_erasure = delete`; the legacy table is untouched.
- **IDN-02 and IDN-08:** `users:invalid_login_row` (and the three org_roles reasons, section 4) is NOT pre-accepted; the
  owner accepts it after Stage B with its count. April has no `local_uniquelogins` table at all.
- **F-17 (April facts; the open questions below are answered by them in part; re-run SHOW COLUMNS and the counts on the
  live backup at Stage B):** `local_syncerrors` has 4,874 rows and the columns id, error, date_created, modified_by,
  mandatory_fields, email, idnumber (no type, sync_file_name, firstname or lastname); `local_userssyncdata` has 749 rows;
  `local_transcript_history` has 0 rows; `local_uniquelogins`, `local_positions` and `local_domains` are absent;
  `$CFG->timezone` is Asia/Kolkata; `local_userdata` has 71 mismatches with `user.open_path` (reported only). Without
  `sync_file_name`, 4,385 of 4,386 user-2 error rows fall inside a user-2 run window and 1 goes to an orphan-day run; all of
  them are tenant 0 (cross-tenant only), so there is no tenant exposure.
- **F-18 (after Stage B):** `step::group_by()` accepts raw columns only, so synthetic runs are per-uploader groups with day
  sub-rows and the report undercounts the synthetic runs created; let it accept an SQL expression (uploader, day), and add
  the person-column privacy export and erase check to `importer_contract`.
- **Answered (signed 2026-09-30):** transcript status normalisation approved as written; admin-uploaded runs get tenant 0;
  `local_uniquelogins` imported; transcript rows do not count toward completion totals; `local_userdata` is a derived mirror
  (`user.open_path` wins, report only); `local_positions` and `local_domains` lookups imported with ids kept.

---

## 11. notifications

**Owner:** `local_sentientia_emails`. **Depends:** none. **Atomic:** no.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_emaillogs (production shape `BZ local/classroom/db/install.php:92-127`; batchid and courseid indexes `BZ local/classroom/db/upgrade.php:412-422`) | local_sentientia_email_log (`SE local/sentientia_emails/db/install.xml:82-118`) | map |
| local_email_logs (only if the table exists with rows) | same | map |
| local_notification_info, _type, _strings | none | declined: configuration, read in place at import time to resolve `legacy_type` |

`local_sentientia_notif_log` is rejected as a target: the navbar treats its `sent` rows without
`timeread` as unread (`SE local/sentientia_notifications/lib.php:22-35`) and `mark_all_read` would
rewrite them (:106-111). Core `{notifications}` is purged by the core cleanup task.

### Column map (local_emaillogs)

```
to_userid -> userid                 as is; for supervisor copies (teammemberid > 0) this is the manager
                                    (BZ local/classroom/classes/notification.php:228-232)
from_userid -> sender_userid        NEW; the enqueuing actor. Every delivered message was sent FROM the support
                                    user and sent_by was overwritten with it (BZ local/notifications/notification.php:64,82,91)
courseid -> courseid                > 0 and exists; else NULL (-1 = custom mail, BZ local/notifications/lib.php:690).
                                    2026-10-07 decision COMMS-N3: the production table has NO courseid column (April
                                    shape), so for a course template the course is taken from moduleid (decisions block)
(tenant rule) -> tenant_id
(const) -> channel 'email'
subject -> subject                  fit 255; NULL -> ''; MASKED for credential rows (below)
(const) -> template_key NULL        mandatory: dedupe, cap and completion stamps key on it
                                    (SE local/sentientia_emails/classes/task/process_rules.php:148-152,202-206,354-375;
                                     SE local/sentientia_emails/classes/delivery_log.php:83-89)
(const) -> rule_id NULL             'NULL for ad-hoc sends' (SE install.xml:86-87)
ni.notificationid -> nt.shortname -> legacy_type   LEFT JOIN (templates are hard-deleted, BZ local/notifications/index.php:47);
                                    fit 100 (source CHAR255, BZ local/classroom/db/install.php:49; target CHAR100, SE install.xml:88)
status -> status                    see status mapping
(derived) -> error_message          NULL for sent; a note for not_sent or a deleted recipient
(const) -> attachment_filename NULL, certificate_issue_id NULL
sent_date / timecreated -> timecreated  status = 1 AND sent_date > 0 -> sent_date; else first non-zero of
                                    timecreated, time_created (if the column exists), timemodified
sent_date -> timesent               NEW; 0 -> NULL
emailbody -> body_html              NEW; NULL for credential rows (below)
(const) -> legacy_source 'bizlms'   NEW in-row marker (no unique index); readers and guards branch on it
not copied (stay in the legacy table, R7): notification_infoid, moduletype, batchid (absent on production, 2026-10-07
  decision F-69), ccto, from_emailid, to_emailid, sent_by, adminbody, attachment_filepath, reminderdays, enable_cc, active
  (BZ local/classroom/db/install.php:95-121, :106-108), the template snapshot. moduleid and teammemberid are READ but not
  copied: they derive the course link (COMMS-N3) and the withheld body of a manager copy (COMMS-N2)
```

`local_email_logs`: to_userid -> userid; from_userid -> sender_userid; courseid (-1 -> NULL); subject;
body_html; sent_date -> timesent; created_date or time_created -> timecreated; status from sent_date > 0
(the writers at `BZ local/notifications/lib.php:599-644,674-691` set no status).

### Credential redaction (applied to subject and body)

The password placeholder is filled from `$touser->userpassword`
(`BZ local/users/classes/notification.php:74,94,111`; registered at `BZ local/notifications/lib.php:408-410`)
for **any** local_users notification type whose template uses it: strings are loaded for module `users`
(`BZ local/users/classes/notification.php:130-137`) and types are matched with LIKE (:42-44). It is also
substituted into the subject (:93). Templates are edited in place and hard-deleted, so today's template
text cannot prove what was sent. So: set `body_html` NULL and mask the subject when the type's
`pluginname` is `users`, or the resolved template subject or body contains `[employee_password]`, or the
template is unresolvable and the row's subject or moduletype suggests the users module. Status 0 rows too.
2026-10-07 decision COMMS-N1: the `moduletype` signal never fires on production (it is '' on all 14,202 April rows) and
an unresolvable template now withholds the body; see the decisions block.

### Tenant rule

`tenant_id` = `tenant::root_for_user()` of the **recipient** (`tenant.php:41-47`), the same as native rows
(`SE local/sentientia_emails/classes/notification_sender.php:42-44`); readers treat 0 as "no tenant"
(`delivery_log.php:114-125`). Fallback only when the recipient is missing or has an empty path: the root
of `ni.open_path` (`BZ local/classroom/db/install.php:64`); else 0. The template path never comes first:
BizLMS matched templates with an unbounded `LIKE '%<root>%'` (`BZ local/notifications/lib.php:485-489`).
2026-10-07 decision F-69: the importer takes the recipient's path from `lookups->user_path`, normalises it with
`tenant_resolver::normalise` and validates it with `tenant::assert_valid`; the template's path is used only when the
recipient has no path, and a recipient path that is present but does not parse is tenant 0, never the template's root.

### Status mapping

- `1` -> `sent`. The task sets 1 after `message_send` returns (`BZ local/notifications/notification.php:110-115`),
  and also **without sending** for a recipient who was ALREADY deleted when BizLMS ran the send (:85-88): keep `sent`
  and set `error_message` "BizLMS marked a deleted recipient sent without delivery". A recipient deleted AFTER the send
  was delivered to and imports as plain `sent` (2026-10-07 decision COMMS-N6; April: 1 of 342 rows to now-deleted users
  gets the note).
- `0`, `NULL`, anything else -> `not_sent` (new value; fits CHAR32), with `error_message`
  "BizLMS queue: not delivered before cutover". BizLMS's own UI counts NULL as not sent
  (`BZ local/notifications/email_status_filters.php:68-72`).
- Never `failed` or `suppressed`: they drive the dashboard tiles (`SE local/sentientia_emails/classes/manage_controller.php:80-93`).

### Side effects to avoid

- No `message_send`, `email_to_user`, `notification_sender::send`. The status-0 backlog must never be sent.
- Never `delivery_log::log()`: under `noemailever` it rewrites status to `suppressed` (`delivery_log.php:34-38`).
- No rows in core `{notifications}`/`{messages}` or `local_sentientia_notif_log`; no events.
- Never set `template_key` or `rule_id`; no inserts into `local_sentientia_email_overrides` or `_rules`.

### Schema additions

`local_sentientia_email_log`: + legacy_source CHAR40 NULL, + sender_userid INT NULL, + timesent INT NULL,
+ body_html TEXT NULL. The new status value `not_sent` is documented in the status COMMENT
(`SE install.xml:96-98`). The map's `legacy_id`, UNIQUE `uix_legacy` and `legacy_meta` are not built (R6, R7).

### Code fixes

1. Guards: `delivery_log::mark_reminders_suppressed_on_completion` (`delivery_log.php:83-89`) and the dedupe
   and cap queries (`process_rules.php:148-152,202-206,354-360,369-375`) add `AND legacy_source IS NULL`.
2. `manage_controller::get_logs_data` (`manage_controller.php:320-334`) and `templates/manage/tab_logs.mustache:38-47`:
   show `legacy_type` when `template_key` is empty; a badge for every status; Sent-from and Sent-on columns
   (parity with `BZ local/notifications/classes/output/renderer.php:216-222`). en + hi strings.
3. A detail view for `body_html` (like `renderer.php:264-287`), gated by `local/sentientia_emails:manage`
   plus the tenant scope, rendered with `format_text(FORMAT_HTML)` and cleaning (BizLMS echoed it raw,
   :280), behind a default-OFF flag.
4. `get_logs()` (`delivery_log.php:157` selects `l.*`) and `export_csv` (:236) select explicit columns;
   only the detail view fetches `body_html`. Stream `export_csv` instead of the 10 000-row cap.
5. Dashboard double count: hide the BizLMS tile (`manage_controller.php:39-44,109-111`, fed by
   `SE local/sentientia_emails/classes/legacy_bridge.php:117-136`) when imported rows exist.
6. (Accepted UNFLAGGED as a bug fix, 2026-10-07 decision COMMS-N5.) `legacy_bridge.php:41,94` filters `ni.costcenterid` via `sql_filter` (`tenant.php:335-349`), which 2022+
   writers never set (`BZ local/notifications/externallib.php:128-130`). Switch to
   `path_filter('ni', 'open_path')` **after** a preflight of the `open_path` format: BizLMS wrapped it as
   `concat('/', ni.open_path, '/')` before LIKE (`BZ local/notifications/lib.php:489`), so values without a
   leading slash may exist, which `path_filter` would not match (`tenant.php:394-398`).
7. Privacy provider (`SE local/sentientia_emails/classes/privacy/provider.php:19-28,86-131`): declare
   `sender_userid` and `body_html`; erasing a sender anonymises `sender_userid` on other users' rows.

### Fixture

Production-shape legacy tables with production NOT NULL constraints on `notification_infoid`,
`from_userid`, `to_userid`, `from_emailid`, `to_emailid`, `moduletype`, `moduleid`
(`BZ local/classroom/db/install.php:95-103`), plus `reminderdays`, `enable_cc`, `active` (:106-108), and
both timestamp dialects. Rows: sent to A in /1; status 0 to B with a password in the body; NULL to C;
timecreated 0 with timemodified set; unresolvable template; sent to a deleted user; a manager copy;
courseid -1; courseid 999999; recipient /1 with a /177 template. **17 + 4 rows** (2026-10-07 decision F-69: the fixture is not ten rows): assert every row imported, zero on
the second run, redaction of subject and body, tenant attribution by recipient, status preserved under
`noemailever`, empty sinks, legacy tables unchanged, reminder dedupe unaffected.

### Verification corrections applied

- `reminderdays`, `enable_cc`, `active` accounted for (they stay in the legacy table).
- Fixture in production shape; row count 10.
- Redaction scope widened to every users-module row and to the subject.
- `legacy_type` length guard.
- Reader cost: explicit columns; body only in the detail view.
- Re-queue note corrected: the classroom and users writers keep the first `timecreated`
  (`BZ local/classroom/classes/notification.php:262-266`; `BZ local/users/classes/notification.php:103-107`).
- Sender semantics documented.
- `open_path` format preflight before the `legacy_bridge` change.

### Open questions

- Accept `not_sent` for queue rows? Answered 2026-09-30: yes (signed `notifications.queue_status`).
- Import bodies at all (DPDP minimisation), or subjects and metadata only? Answered: yes, with credentials redacted
  (`notifications.import_bodies`); 2026-10-07 decisions COMMS-N1 and COMMS-N2 withhold the bodies named above.
- Keep sender identity? Retention period for imported rows? Answered: sender kept (`notifications.keep_sender`), no
  purge (`notifications.retention = keep_no_purge`).
- Status-1 rows to deleted recipients: `sent` with a note (proposed) or `suppressed`? Answered: `sent` with a note, for a
  recipient already deleted at send time (`notifications.deleted_recipient_sent`, wording corrected 2026-10-07, COMMS-N6).
- Parity, not import: which BizLMS notification types have no Sentientia rule once BizLMS stops sending? Answered
  2026-10-07 (COMMS-N7): course enrolment, learning-path enrolment and the manager copy of course completion; see
  section 21, G10.

### Decisions of 2026-10-07

- **COMMS-N1 (credentials; blocks Stage B):** the redactor missed realistic shapes (a table cell `<td>Password</td><td>X</td>`,
  `Password<br>X`, a value containing `;` or `&`, and a welcome subject such as 'Your Airpay Academy account'), and the
  `moduletype` signal never fires on production. BizLMS hard-deletes templates, and the `users_welcome_email` template covers
  839 April rows, every one built with the plaintext password; if that template were deleted before cutover those bodies
  would become unresolved-template rows and be copied into `body_html`, from where `email_detail.php` and DPDP exports show
  them. Rule now: when the template is unresolvable (`$template === null` or its `pluginname` is NULL) `body_html` is NULL and the row
  warns `credentials_withheld:unresolved_template`; the subject is masked when `redactor::subject_suggests_credentials()` or
  the new `redactor::text_mentions_secret()` matches (password, passwd, pwd, passcode, credential(s), otp, pin, secret, token,
  no separator required), otherwise scrubbed; `redactor::scrub()` makes the separator optional when the gap holds a tag or
  line break and lets the value run to whitespace, '<' or a quote, so ',', ';' and '&' no longer end it. `verify()` adds
  `imported_text_with_unredacted_secret`; preflight counts unresolved-template rows that mention a secret word. Expected on
  April after the fix (to be re-measured read-only): 14,197 sent, 5 not_sent, 839 withheld, 0 masked-with-body. No
  decisions-file key: this is a safety rule, not an owner choice. F-72: `scrub()` over-redacts the bare words 'pass' and
  'pin'; accepted as conservative (it changes 0 of the 13,363 kept April rows) and documented in the redactor docblock;
  optionally treat a '/' or '//' recipient path as empty so the template fallback applies (0 April rows).
- **COMMS-N2 (manager copies; blocks Stage B):** a copy sent to a manager (`teammemberid` > 0; 1,921 April rows, all
  `course_complete`) is imported WITHOUT its body, and the team member's whole-word first and last name is scrubbed from the
  subject (`notifications.team_member_copy_body = withhold`; warning `team_member_copy_body_withheld`). The body names the
  member and `teammemberid` is not carried, so an erasure of that person could never reach it; the member's own
  `course_complete` row (1,919 within one hour) already holds the same message and the legacy table keeps the exact original.
  `verify()` asserts that no imported row whose source row has `teammemberid > 0` carries a body. A later `subject_userid`
  column can backfill the bodies. April: 1,902 bodies and 4 subjects contain the member's first name; 685 distinct members,
  75 now deleted; no copy crosses two live tenants.
- **COMMS-N3 (course link; blocks Stage B):** `courseid` = the column when it exists and is > 0 and the course exists; else
  `moduleid` when the template's moduletype is 'course', `moduleid` > 1 and the course exists (warning `course_from_moduleid`);
  else NULL (`notifications.course_link = moduleid_for_course_templates`). 12 of the 15 April templates are 'course'
  templates; 5,270 of 5,316 `course_enrol` rows and 4,139 of 5,783 `course_complete` rows point at an existing course (9,409
  in all), and most bodies name it. Imported rows never feed the reminder engine.
- **COMMS-N4:** a blank 'Sent from' on an imported row means the BizLMS system (the support user, `from_userid` -20, mapped
  to `sender_userid` NULL) sent it, exactly as BizLMS's own list showed (`BZ local/notifications/email_status_filters.php:43`
  takes the sender name from a `{user}` subquery, NULL for -20). April: 11,099 of 14,202 rows. Accepted as parity and noted in
  the state card and the visual-evidence README.
- **COMMS-N5:** the `legacy_bridge` template-filter change (code fix 6) ships WITHOUT a flag, accepted as a bug fix: before
  it, the Templates tab and template preview were empty for everyone on the production shape (the SQL named `ni.costcenterid`,
  which April's `local_notification_info` does not have; it threw and the catch swallowed it). It builds select, order and
  filter from the columns that exist, matches whole path segments and fails closed when neither column exists (ADR-031).
  Measured: 15, 10 and 4 templates for cross-tenant, `/1` and `/77` callers. Sentientia is not live, so BizLMS production is
  untouched. Evidence: a Templates tab screenshot as a scoped (`/77`) admin in the UAT pass.
- **COMMS-N6:** `notifications.deleted_recipient_sent` wording corrected in the decisions file, ADR-032 and the status
  mapping above: the note applies only to a recipient already deleted when BizLMS ran the send. F-67: the rule reads the
  user's `timemodified`, so anything that rewrites deleted users' rows (the DPDP anonymiser, an HRMS re-sync, cleanups) would
  make undelivered rows look delivered. Runbook: run the notifications feature before any step that updates deleted user
  rows; preflight warns when many deleted recipients share one `timemodified` or have one later than the newest `sent_date`.
- **COMMS-N7 (`gaps.notification_sender_parity`):** see section 21, G10.
- **COMMS-C1:** `notifications:orphan_user`, `request:orphan_user`, `request:orphan_item` and `request:orphan_request` are NOT
  pre-accepted (all 0 on April; `legacy_logs` needs no reason because pathless skips nothing). They are added to the top-level
  `accepted_reasons` list after Stage B, with counts. F-82: the older name `accept_needsowner.<feature>.<reason>` in section 23
  and in the emails state card is wrong; the loader reads a top-level list of `"feature:code"` strings.
- **COMMS-C2 (recommended future flips, decided by Nitin after the screenshots; none flipped):**
  `sentientia.emails.imported_history.enabled` ON, `sentientia.emails.imported_body_detail.enabled` ON,
  `sentientia.request.imported_history` ON only after COMMS-R1 has landed, `sentientia.legacy_logs.report.enabled` OFF.
  BizLMS showed an e-mail log list and a detail view that echoed the full body, password e-mails included, to anyone holding
  `local/notifications:view` (`BZ local/notifications/classes/output/renderer.php:213-225,264-287`); Sentientia shows less
  (credential and manager-copy bodies withheld, tenant-scoped). Evidence (desktop and mobile): the Logs tab,
  `email_detail.php`, the Templates tab, My requests, Pending approvals, All requests and `admin_log.php`.
- **Follow-ups:** F-60 and F-87 (before any dev copy of a Stage B database is shared: `mask_pii_for_dev.php` updates a
  `to_email` column that `local_sentientia_email_log` does not have and never masks imported subjects or bodies; drop that
  UPDATE, mask the subject and set `body_html` NULL for `legacy_source = 'bizlms'` rows, NULL `decision_note` on imported
  requests, mask `admin_log` descriptions), F-61 (with P0.4, `migration_parity_check.php` takes `--decisions` and
  `--expect-decisions-hash`; notifications `verify()` reads two decisions and would report `verify_error` without them),
  F-62 (see ADR-032), F-63 (`timesent` only when delivered; April: 0 rows), F-64 (index `idx_sender_userid`, before cutover,
  a new version step if 2026093001 already reached UAT), F-65 (before `imported_body_detail.enabled` is flipped: strip external
  images and tracking pixels from imported bodies before `format_text`, and prefix CSV cells that start with `=`, `+`, `-`
  or `@`), F-66 (test hygiene), F-68 and F-69 (April production shape: `local_emaillogs` has `moduleid`, `teammemberid` and
  `emailbody` but no `courseid`, `batchid` or `time_created`; `local_email_logs` is absent; every template `open_path` has a
  leading slash; confirm with SHOW COLUMNS on the live backup), F-70 (state card header names the retired plugin and an old
  version), F-71 (visual evidence for the flagged UI).

---

## 12. recompletion

**Owner:** `local_sentientia_recompletion`. **Depends:** none. **Atomic:** no.

The source is the community plugin `local_recompletion` 2023012600 (Catalyst IT,
`BZ local/recompletion/version.php:17-31`), not eAbyas code. It has **16 tables**
(`BZ local/recompletion/db/install.xml:7-276`); `local_recompletion` and `local_recompletion_sas` were
dropped by its own upgrade (`BZ local/recompletion/db/upgrade.php:201-206,322-331`). Short names below:
`check_recompletion.php` = `BZ local/recompletion/classes/task/check_recompletion.php`;
`mod_*.php` = `BZ local/recompletion/classes/plugins/mod_*.php`; `engine` =
`SE local/sentientia_recompletion/classes/recompletion_engine.php`.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_recompletion_config (`install.xml:134-147`) | local_sentientia_recompletion_rules (`SE local/sentientia_recompletion/db/install.xml:10-44`), one DISABLED rule per course | map, `#local_recompletion_config.course`, sourceid = course id |
| logstore_standard_log WHERE eventname = `\local_recompletion\event\completion_reset` (fired once per reset, `check_recompletion.php:214-223`) | local_sentientia_recompletion_history (`SE install.xml:51-78`) | map, sourceid = log id |
| local_recompletion_cc (`install.xml:7-25`) | history row (only when no log event matches) + NEW archive row (itemtype `course_completion`) | map, subkey `''` = archive row, `history` = inferred history row |
| local_recompletion_cc_cc, _cmc, _qa, _qg, _sst, _ltia, _qr (`install.xml:26-176`) | NEW local_sentientia_recompletion_archive | map |
| the 7 `_qr_*` child tables (`install.xml:177-276`) | archive, itemtype `questionnaire_answer`, parent = the `_qr` archive row | map |
| config_plugins plugin `local_recompletion` | none (fallback duration only) | declined: site defaults (`BZ local/recompletion/settings.php:33-65`) |

### Column maps

```
local_recompletion_config -> rules (all name/value rows of one course fold into one rule)
  course -> courseid              skip rows whose course no longer exists; the site course never has a row
                                  (BZ local/recompletion/recompletion.php:35-38)
  'enable' -> enabled = 0 ALWAYS  legacy acted only on '1' (check_recompletion.php:63); the Sentientia engine runs
                                  every enabled rule daily with no flag (engine:37-38; classes/task/run_rules.php:18-20;
                                  db/tasks.php:7-16); Sentientia rules default enabled = 1 (SE install.xml:26)
  'recompletionduration' (SECONDS) -> period_days = max(1, ceil(value / 86400))  (recompletion_form.php:45-46;
                                  check_recompletion.php:66-67). '0', '' or non-numeric -> config_plugins duration
                                  (settings.php:33-35) else 365, and flag the course
  (const) -> trigger_type 'completion'; fixed_date NULL (check_recompletion.php:66-67)
  'deletegradedata' -> reset_grades ('1' -> 1 else 0) (check_recompletion.php:188)
  'quiz' -> reset_attempts ('1' delete -> 1; '0' or '2' extra attempt -> 0) (BZ local/recompletion/locallib.php:27-29;
                                  mod_quiz.php:96-118)
  (const) -> costcenterid 0       legacy selection has no tenant filter (check_recompletion.php:61-67); 0 = all tenants
  (derived) -> name = core_text::substr('Legacy recompletion: ' . course.shortname, 0, 200)  (char 200, SE install.xml:13)
  (const) -> timecreated, timemodified = import time (the config has no timestamps)
  (derived) -> last_run_at = MAX(timecreated) of this course's completion_reset log rows, else NULL; last_run_resets NULL
  ALL name/value rows -> legacy_config JSON (kept: the rule cannot express email templates, extra attempts,
                                  archive switches, assign/lti/pulse/questionnaire choices; index.php and the engine
                                  parity fixes read it)

logstore completion_reset -> history
  relateduserid -> userid; courseid -> courseid; timecreated -> timecreated (real reset time)
  origin 'cli' -> reason 'cron', reset_by_userid NULL (engine:176-177)
  origin 'web' AND userid = relateduserid -> reason 'manual', reset_by_userid = userid (self reset,
                                  BZ local/recompletion/resetcompletion.php:44-55)
  origin 'web' AND userid <> relateduserid -> reason 'legacy', ALWAYS (2026-10-07 decision F-53: the log has no url,
                                  and the reset page and a browser-run cron fire the same event,
                                  check_recompletion.php:214-223)
  (matched cc row).timecompleted -> previous_timecompleted; else the latest core course_completed log row before
                                  the reset; else NULL (history.php:70-71 shows '-')
  ruleid -> the imported rule for courseid, else 0 (engine:462)
  reset_grades -> legacy deletegradedata; reset_attempts -> 1 if any _qa row falls in this cycle, else quiz == '1'
  dryrun 0; source 'legacy'; time_inferred 0
  An inferred history row (below) already made for the same cc row -> fold into it: update timecreated to the log
  time, time_inferred 0, reason, reset_by_userid. No second history row.

local_recompletion_cc -> archive (itemtype course_completion) and, when no log event matches, history
  archive: userid, course -> courseid; timecompleted -> timeevent (else timestarted, else timeenrolled);
           state = timecompleted > 0 ? 'complete' : 'incomplete'; payload = full row JSON; historyid = its cycle
  history (subkey 'history'): timecreated = MIN(timecompleted + legacy duration,
           the next cycle's first evidence for that user and course (next cc timeenrolled/timestarted or the earliest
           later archive row)) when that time is not later than the import; otherwise the cycle's latest source evidence
           + 1 second, never earlier than the previous cycle's end, with warning inferred_reset_from_last_evidence (the
           import time is NEVER the value; 2026-10-07 decision LRN-01); time_inferred 1; reason 'legacy'; reset_by_userid NULL;
           previous_timecompleted = cc.timecompleted; source 'legacy'
  cycle match per (userid, course): each cc row belongs to the earliest unmatched reset event with
           event.timecreated >= cc.timecompleted; a cc row with NULL timecompleted (manual reset of an incomplete
           user, resetcompletion.php:64-66) takes the earliest unmatched event after timestarted or timeenrolled
           pairing makes an uncapped pass first and then a start-only cap pass; a pair it cannot settle keeps its rows
           apart with the warning reset_pairing_unclear (2026-10-07 decision F-53)

cc_cc  -> criteria_completion: criteriaid -> instanceid; gradefinal -> grade; timecompleted -> timeevent; state
cmc    -> activity_completion: coursemoduleid -> cmid; course = 0 -> course_modules.course (upgrade.php:209-217);
          completionstate -> state (0 incomplete, 1 complete, 2 complete_pass, 3 complete_fail, install.xml:51);
          timemodified -> timeevent; viewed, overrideby -> payload
qa     -> quiz_attempt: quiz -> instanceid; cmid from course_modules; course = 0 -> quiz.course (upgrade.php:232-238);
          state verbatim (inprogress|overdue|finished|abandoned, install.xml:75); sumgrades -> grade (raw marks);
          timefinish -> timeevent (else timemodified, else timestart); payload = FULL row JSON, incl. uniqueid
          (the question_usages link: quiz_attempts rows were deleted directly, mod_quiz.php:116)
qg     -> quiz_grade: quiz -> instanceid; grade -> grade; timemodified -> timeevent; course = 0 -> quiz.course
sst    -> scorm_track: scormid -> instanceid; element -> itemkey; value -> state for lesson/completion/success status
          elements; value -> grade for score.raw when numeric and in range; else NULL with the raw value in payload
ltia   -> lti_grade: toolid -> instanceid; courseid from enrol_lti_tools.contextid (no course column,
          mod_lti.php:100-101); lastgrade -> grade; lastaccess -> timeevent
qr     -> questionnaire_response: questionnaireid -> instanceid; complete 'y'/'n' -> complete/incomplete;
          grade -> grade (clamped); submitted -> timeevent; originalresponseid -> payload and join key
qr_bool, qr_date, qr_m, qr_other, qr_rank, qr_single, qr_text -> questionnaire_answer: parentid = the qr archive row
          whose originalresponseid = response_id (children keep the ORIGINAL response id, mod_questionnaire.php:122-127);
          userid, courseid, historyid, timeevent from the parent; question_id -> itemkey; choice_id or response -> state
          or payload; rankvalue -> grade (clamped). An orphan child (no parent) is skipped and reported.
all archive rows: historyid = the earliest reset of (userid, courseid) at or after the row's own time, else 0
          (ambiguous rows keep 0 rather than guess); grade is number(10,5): out of range or non-numeric -> NULL,
          raw value in payload
```

### Tenant, status, side effects

- **Tenant:** rules keep `costcenterid` 0 (legacy semantics: every completer, any tenant); only
  cross-tenant callers see them (`SE local/sentientia_recompletion/classes/rule_access.php:61-69,93-102`).
  History and archive have no tenant column; readers derive it from `{user}.open_path` at read time
  (`SE local/sentientia_recompletion/history.php:32-39`; `rule_access.php:113-115`).
- **Status:** as in the column map. `deletescormdata` is a dead config name (`upgrade.php:287-289` vs
  `mod_scorm.php:94`): such courses had "do nothing" for SCORM.
- **Side effects:** never call `reset_user_in_course`, `bulk_reset`, `run_rule` or `run_all`
  (engine:257-313,323-339). No messages (`engine:188-195,216-229`). Never write archived rows back into
  live core tables. Never stamp history `timecreated` with import time: the engine skips a user and course
  with a history row newer than now-86400 (`engine:149-157`). Never `dryrun = 1` (greys the row,
  `SE local/sentientia_recompletion/templates/history.mustache:26,36`). Never uninstall
  `local_recompletion` before sign-off.

### Schema additions

- `local_sentientia_recompletion_history`: + `source` char(20) NOT NULL DEFAULT 'engine', + `time_inferred`
  int(1) NOT NULL DEFAULT 0. (`legacy_ref` not built, R6.)
- `local_sentientia_recompletion_rules`: + `legacy_config` text NULL. (`legacy_ref` not built.)
- NEW `local_sentientia_recompletion_archive`: id; historyid int NOT NULL DEFAULT 0; userid int NOT NULL;
  courseid int NOT NULL DEFAULT 0; itemtype char(30) NOT NULL; cmid, instanceid, parentid int NULL;
  itemkey char(255) NULL; state char(30) NULL; grade number(10,5) NULL; timeevent int NULL; payload text
  NOT NULL; timecreated int NOT NULL. FKs historyid, userid, courseid; indexes (userid, courseid),
  (historyid), (parentid). The map's `source_table`/`source_id` UNIQUE is replaced by the map. The payload
  holds personal data (userid, overrideby, free-text answers): declare it.
- Both trees are byte-identical today; ship both.

### Code fixes

1. `SE local/sentientia_recompletion/classes/privacy/provider.php:54-68,71-93,95-135,137-147,175-191`: add
   the archive table; erasure redacts userid and scrubs payload (userid, overrideby, `qr_text`/`qr_other`
   free text); DPDP anonymise scrubs actor ids.
2. `history.php:24`: add `courseid`/`userid` filters (the event URL already sends courseid,
   `SE local/sentientia_recompletion/classes/event/completion_reset.php:84-86`); a "legacy" badge from
   `source`; `~` before inferred dates; "self" when reset_by = userid; a link to the evidence view.
3. NEW evidence view (`history_detail.php`) reading the archive with the `history.php:32-39` tenant filter,
   quiz grades scaled by `quiz.grade / quiz.sumgrades`, en + hi labels, behind a new default-OFF flag in a
   new `db/feature_flags.php` (the plugin has none).
4. `index.php`: legacy marker and `legacy_config` summary; remove the dead `bulk_reset.php` link (:60).
5. Engine parity, required before anyone enables an imported rule: course rules skip `c.enablecompletion = 1`
   (engine:81-86); `quiz_delete_attempt` gets the first quiz for every attempt (:303-311); SCORM tracking is
   always wiped (:269-274); nothing is archived before deletion (:257-313); whole days vs seconds;
   hard-coded English messages (:189-194,224-227).
6. `classes/task/run_rules.php:18-26`: gate behind a default-OFF flag, so a restored database plus imported
   rules cannot start resetting on the first 03:15 run (`db/tasks.php:7-16`).
7. `SE local/sentientia_compliance_report/README.md:46-47` claims a history read no code performs:
   implement or correct. `SE local/sentientia_recompletion/README.md:23-26` has the wrong cron time and a
   bulk UI that does not exist: correct.

### Fixture

Ship `tests/fixtures/bizlms/local_recompletion.install.xml`; create the **16** tables in
`setUpBeforeClass`. Users `/1/5`, `/77`, `/177`, NULL, one deleted; a completion-enabled course `/1` with a
quiz and a SCORM, an LTI tool in the course context, a questionnaire id used only as a number. Config for
the course: enable 1, duration 31536000, deletegradedata 1, archivecompletiondata 1, quiz 1, archivequiz 1,
scorm 1, archivescorm 1, assign 2, email subject and body, `deletescormdata` 1; a second course with only
enable 0; a third with duration 0. User A: two cycles (cc 2023-03-01 and 2024-03-05; log resets 2024-03-02
and 2025-03-06). User B: a self reset with no cc row. User C: a cc row with no log row, completed
2025-12-01 and a later enrolment 2026-01-20 (inferred time capped at the next evidence, not
2026-12-01). cmc/qa/qg/sst rows in each cycle plus old-format course = 0 rows; one ltia; a qr with qr_text
and qr_bool children; an orphan qr_single. Then add the log row for C and re-run: the inferred row is
upgraded, not duplicated. Assert rules (3, all disabled, costcenterid 0, period 365, the zero-duration
course flagged), history rows and reasons, cycle attachment, course = 0 resolution, qr parents, orphan
reported, payload round-trip, no events, messages or emails, core tables unchanged, `run_all()` resets 0,
privacy scrub.

### Verification corrections applied

- 16 tables, not 17.
- Inferred reset time capped at the next cycle's first evidence and at import time (never in the future). 2026-10-07
  decision LRN-01: when neither completion plus duration nor the next cycle's evidence gives a time at or before the
  import, the cycle's latest source evidence + 1 second is used, never the import time.
- Rule name truncated to 200.
- Archive grade clamped; raw value in payload.
- `qa` payload is the full row.
- Inferred row upgraded when the log row appears later (idempotence gap closed).
- `origin 'web'` by an admin is 'manual' only with page evidence.
- Duration 0 or empty falls back and is flagged.

### Open questions

- I-20: counts per table; which courses have enable = '1'; is recompletion used on live at all?
- Production logstore `loglifetime`; does the Stage B restore carry `logstore_standard_log` in full?
- Enable imported rules at cutover (after the engine parity fixes and a dry-run pass), or keep them off?
  Which legacy behaviours must be rebuilt (custom email, extra attempts, LTI grade reset)?
- Rule tenant: keep costcenterid 0, or one rule per tenant root among the completers?
- Where learners see past cycles: this plugin, the transcript page, or the compliance dashboard?
- One generic archive table with JSON payload (proposed), or 1:1 mirror tables?
- Import teacher-preview attempts (`qa.preview = 1`)?
- Should the engine archive before it deletes, into the same table?
- Confirm that deploying upstream `local_recompletion` on 5.2 instead is out of scope.

### Decisions of 2026-10-07

- **LRN-01 (inferred reset time):** see the history column map above. Signed key
  `recompletion.inferred_reset_without_evidence = latest_source_evidence_plus_1s`. Deterministic, so Stage B and cutover
  produce the same rows; the +1 second keeps the cycle's own last row attached under `evidence::ends_cycle_of`, which
  requires a row to be strictly before an inferred reset.
- **LRN-02 (history page):** with `sentientia.recompletion.evidence_view` OFF, `history.php` filters out `source = 'legacy'`
  rows, so no Legacy badge and no '~' rows; ON shows them, and engine rows always show. The imported-rule marker on
  `index.php` stays visible (a configuration safety label). Signed key `recompletion.legacy_rows_on_history_page =
  behind_evidence_view_flag`. Visual evidence of `history.php` with the flag OFF and ON.
- **LRN-03 (DPDP):** erasure through the DPDP flow keeps the archived evidence row keyed to the anonymised user and CLEARS
  free text that can identify the person (teacher feedback, typed questionnaire answers, text typed into SCORM), the rule
  the platform already applies to attendance notes and exemption reasons; core erasure empties the same keys. The payload
  `userid` and actor ids stay (an actor id is the actor's own data, scrubbed when that actor is erased). Signed key
  `recompletion.dpdp_archive_free_text = cleared_record_kept`.
- **LRN-04 (imported rules):** an imported rule (`legacy_config` set) cannot be enabled in the edit form and the engine skips
  rules with `legacy_config IS NOT NULL` (counted as `skipped_imported`) until a later decision says engine parity (code fix
  5) is done; the `legacy_enabled_warning` redirect becomes unreachable and is removed. A native rule is the way to reset
  learners meanwhile. Signed key `recompletion.imported_rule_enable = blocked_until_engine_parity`.
- **LRN-05 (engineering contract, no decisions-file key):** `reset_user_in_course()` throws on a rollback (it never swallows
  one) and the callers (`run_rule()`, `bulk_reset()`, `run_all()`) catch per learner, count `failed` and carry on, so one
  failing learner no longer ends a cron batch; the 'no reset without its archive' guarantee holds.
- **LRN-06 and F-55 (recommended future flip, NOT decided; today's EOD UAT session):** the 2026093001 deploy turns the 03:15
  reset task OFF (`sentientia.recompletion.run_rules`, default OFF). Keep it OFF on UAT until the enabled
  `costcenterid = 0` rules on UAT (list ids and course ids only; they may have been made by a tenant admin before the
  ADR-031 fix and would reset every tenant) have each been confirmed or disabled by Nitin, then ON only if a UAT test needs
  scheduled resets. At cutover keep it OFF until a native rule Airpay needs exists. April: 0 rows in all 16 tables and 0
  Sentientia rules, so OFF is today's behaviour.
- **F-56 (Stage B):** the rehearsal copy is at plugin 2026092500 (no archive table), so upgrade step 2026093001 has never run on
  real MySQL. `eventname` on the 2.59M-row standard log is unindexed and is scanned about six times (preflight, `events_step`,
  `resets()`, `completed_before()`, fingerprint, `verify()`): run the upgrade on the rehearsal copy and time a recompletion
  `--preflight`, for the maintenance-window estimate (ADR-032 open decision 7).
- **F-57 (after Stage B, only if Stage B shows recompletion rows; April: 0):** `notify()` formats the previous completion in
  the sender's language; `legacy_summary` shows an unexpected choice value as nothing; `evidence.php` reads legacy tables
  with `$DB` and misses `course = 0` rows and `cc_cc`, `ltia` and `qr` times as next-cycle evidence; evidence with
  `historyid` 0 has no link from `history.php` (the link needs visual evidence).
- **Open questions above, answered (signed 2026-09-30):** enable imported rules at cutover: no, all disabled; rule tenant:
  global; where learners see past cycles: the history page and evidence view, flag OFF; archive shape: one generic table with
  a JSON payload; the engine archives before it deletes: yes, required before any rule is enabled; teacher-preview attempts:
  skipped; deploying upstream `local_recompletion` on 5.2: out of scope. April: 0 rows in all 16 `local_recompletion_*`
  tables and 0 reset events in the log, so no recompletion decision changes the April rehearsal.

---

## 13. cart

**Owner:** `local_sentientia_cart`. **Depends:** none. **Atomic:** no.

Short names: `BC` = `BZ local/biz_cart/`; `PGW` = `BZ payment/gateway/airpay/`; `SC` =
`SE local/sentientia_cart/` (both trees identical for install.xml, upgrade.php, version.php,
cart_manager.php, list_orders.php, history.php, invoicer.php, return.php, callback.php, and **both trees
have `amd/`**). BizLMS writes one history row per item grouped by an integer cart identifier
(`BC classes/biz_cart_history.php:652-667`); Sentientia keeps one history row per order with an
`items_json` snapshot (`SC db/install.xml:36-80`, items_json :45-46). `callback.php:104-105` and
`return.php:24-25` load it by orderid with MUST_EXIST, so the import aggregates by identifier.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_biz_cart_history (`BC db/install.xml:7-46`) grouped by identifier | local_sentientia_cart_history, one row per order | map, `#local_biz_cart_history.identifier`, sourceid = identifier; every line also gets a primary row `merged` into its order (sourcetable `local_biz_cart_history`, sourceid = line id) |
| local_biz_cart_id (`BC db/install.xml:113-121`) | local_sentientia_cart_id (`SC db/install.xml:13-28`), id = identifier (P) | fold into the order; unreferenced ids are archived (page views that produced no order) |
| local_biz_cart_ledger (`BC db/install.xml:67-112`) | local_sentientia_cart_ledger (`SC db/install.xml:88-118`), 1:1 | map; synthesized payment rows (if approved) are fan-outs of the history line, subkey `synth_payment` |
| local_biz_cart_invoices (`BC db/install.xml:122-135`) | local_sentientia_cart_invoices (`SC db/install.xml:146-186`) | map; natural UNIQUE invoice_number (:179) |
| local_biz_cart_credits (`BC db/install.xml:47-66`) | NEW local_sentientia_cart_credit_txn + derived local_sentientia_cart_credits balance (`SC db/install.xml:125-139`) | map; balance row unique per userid |
| paygw_airpay, paygw_course_enrolmentlog, paygw_airpay_errorlog, core payments (component local_biz_cart) | none; folded as gateway evidence | declined as tables (payment-gateway group decides) |

### Column maps

```
history group (identifier N) -> cart_history
  identifier -> orderid            the SAME number (receipts BC receipt.php:73,198; payments.itemid
                                   BC classes/biz_cart_history.php:201-202). NULL/0 identifier: the line becomes its own
                                   order with orderid allocated from the new sequence above the floor, noted in notes
                                   and the report (return.php needs an orderid, return.php:20,24-25)
  userid -> userid                 exactly one distinct value per group, else abort the group and report
  (buyer's user.open_path) -> costcenterid   tenant::root_for_user (tenant.php:41-47), as get_or_open_cart does
                                   (SC classes/cart_manager.php:63-67); history.costcenter is not usable (BC install.xml:23;
                                   BZ local/courses/classes/biz_cart/service_provider.php:76,331)
  lines ORDER BY id -> items_json  one object per line:
      courseid = itemid if componentname 'local_courses' AND area 'option' (service_provider.php:59-67,315-317), else 0
      name = itemname; shortname = course.shortname if the course exists
      price = undiscounted net line price; discount = COALESCE(discount, 0); discount_pct 0; tax 3dp
      line status 'paid' (2) | 'cancelled' (3) | 'not_completed' (0, 1)
      legacy = {historyid, componentname, area, itemid, payment, paymentstatus, usecredit, taxpercentage,
                taxcategory, canceluntil, serviceperiodstart, serviceperiodend}  (no actor ids)
      rebookitem lines: itemid was swapped to the original item while componentname stays local_biz_cart
      (BC classes/biz_cart_history.php:587-594): courseid 0, the itemid kept in legacy
  totals in integer paise over lines with paymentstatus IN (2,3), or all lines when none; price already has the
      discount subtracted (BC classes/biz_cart.php:1406-1413,1427-1439); Sentientia total = subtotal - discount + tax
      (SC cart_manager.php:293-310):
      itempriceisnet = 0 (BC biz_cart.php:1561-1569): subtotal = SUM(price + discount - tax); discount = SUM(discount);
                                                      tax = round(SUM(tax), 2); total = SUM(price)
      itempriceisnet = 1 (BC biz_cart.php:1551-1560): subtotal = SUM(price + discount); tax = round(SUM(tax), 2);
                                                      total = SUM(price) + tax
  currency -> currency             strtoupper(trim()); one value per group; '' or false -> 'INR'; must match ^[A-Z]{3}$
                                   (PARAM_ALPHA readers, SC classes/external/list_orders.php:146; get_order.php:77)
  paymentstatus (all lines) -> status   see status mapping
  payment 0 -> gateway = payments.gateway if a core payments row exists, else 'airpay' if a paygw_airpay row exists,
            else 'online'; 1 'cashier', 2 'credits', 3 'cashier_cash', 4 'cashier_debitcard',
            5 'cashier_creditcard', 7 'cashier_manual' (BC lib.php:38-50)
  gateway_ref <- from the paygw_airpay row of the order with status = 2 (latest id if several): its
            paygw_course_enrolmentlog.transactionid (PGW process.php:135-141), else paymentid (PGW process.php:100),
            else 'payments:' . payments.id. Other attempts go to the ledger payload only.
  billing_name/email/phone/address/gstn -> NULL (BizLMS stores none; readers join the user, code fix)
  notes <- 'Imported from BizLMS: identifier N, legacy history ids [...], method M' + anomalies
  timecreated <- MIN(COALESCE(timecreated, timemodified))   (BC install.xml:28 nullable)
  timepaid <- earliest imported payment_received ledger row; else MAX(timemodified) of the paid lines (set to time()
            on success, PGW process.php:117-119); else paygw_course_enrolmentlog.timecreated (reset at success); else the
            'Order Successfull' errorlog row's timecreated (PGW process.php:145-153). NULL for abandoned orders.
            (paygw_airpay.timemodified is always 0: create_order writes ->modified, PGW classes/airpay_helper.php:96-97.)
  timemodified <- MAX(timemodified)
  (const) -> legacy_source 'bizlms'   NEW in-row marker; guards in mark_paid, mark_failed and refund branch on it

local_biz_cart_id -> local_sentientia_cart_id
  identifier -> id (import_record); buyer -> userid (the legacy table has no userid column, BC install.xml:115-116)
  legacy timecreated of that id -> reserved, else the order's timecreated
  finalise(): sets `local_sentientia_cart/bizlms_order_floor` to the highest imported order number (April: 5), then
  reset_sequence; `reserve_order_number()` places the placeholder row at the first native checkout, so new orders never
  reuse a legacy number (2026-10-07 decision cart.order_number_floor: this replaces the finalise() placeholder row this map
  first planned; checkout() runs outside any DB transaction, so the reset is not refused there)

local_biz_cart_ledger -> cart_ledger (1:1; insert-only on both sides, BC biz_cart.php:1453-1458)
  identifier -> historyid, orderid of the imported order; 0 when there is none (NOT NULL DEFAULT 0, SC install.xml:91-94)
  event_type:  ps 2 and itemid > 0 -> 'payment_received' (BC biz_cart_history.php:286-290,595-605)
               ps 2, itemid 0, userid > 0, credits < 0, payment NOT IN (6,8,9) -> 'legacy_credit_redeemed' (biz_cart.php:931-958)
               ps 2, itemid 0, payment IN (6,8) -> 'legacy_credit_payout' (BC classes/biz_cart_credits.php:299-313)
               ps 2, itemid 0, payment 9 -> 'legacy_credit_correction' (BC classes/form/modal_creditsmanager.php:165-181)
               ps 2, itemid 0, userid 0, area 'cash' -> 'legacy_cash_drawer' (BC classes/form/modal_cashout.php:86-100;
                                                          modal_cashtransfer.php:109-139)
               ps 3 -> 'legacy_cancel_to_credit' (BC biz_cart.php:1240-1243,1489-1502)
               anything else -> 'legacy_other', reported
  price -> amount (COALESCE 0, 2dp); currency as above; payment -> gateway (+ 6 'credits_payback_cash',
  8 'credits_payback_transfer', 9 'credits_correction'); usermodified -> initiatedby; annotation -> reason
  userid, itemid, itemname, tax, taxpercentage, taxcategory, discount, credits, fee, componentname, costcenter, accountid,
  payment, paymentstatus, canceluntil, area, schistoryid, and the chosen paygw_airpay row (ap_orderid, status, cost,
  paymentid) plus the other attempts -> payload_json
  timecreated -> COALESCE(timecreated, timemodified, 0)
  SYNTHESIZED (if approved): for each line with paymentstatus 2/3 that has no ps = 2 ledger row (match schistoryid, or
  identifier + itemid + userid), a 'payment_received' row: amount = line price, the order's gateway and gateway_ref,
  payload {"synthesized_from":"local_biz_cart_history","historyid":..}. PGW process.php:108-122 marks lines paid and writes
  no ledger row and no core payments row.

local_biz_cart_invoices -> cart_invoices  (writer: BC classes/invoice/erpnext_invoice.php:153-166; observer BC classes/observer.php:80-98)
  identifier -> historyid, orderid; no imported order (aborted or skipped group) -> skip and report (historyid is NOT NULL
               with fk_history, SC install.xml:149,180)
  invoiceid -> invoice_number = 'ERPNEXT-' . invoiceid; longer than 50 -> abort and report (SC install.xml:151)
  order userid, costcenterid, items_json -> userid, costcenterid, line_items_json; subtotal - discount -> subtotal; total
  billing_name '' (NOT NULL); cgst, sgst, igst 0 (ERPNext computed the tax); status 'legacy_external'; timecreated

local_biz_cart_credits -> credit_txn (the BizLMS table is a journal; current balance = highest id,
    BC biz_cart_credits.php:64-75,205-216)
  userid; costcenterid = root of the user's open_path; credits -> amount (signed); balance -> balance_after;
  currency ('' -> INR; flag anything but INR); usermodified -> initiatedby;
  event_type from the ledger row of the same user with equal |credits| and timecreated within +/-5 s:
    legacy_cancel_to_credit -> earned_cancellation; legacy_credit_correction -> correction;
    legacy_credit_redeemed -> redeemed; legacy_credit_payout -> payout; none -> legacy_unclassified (reported)
  matched ledger row -> ledgerid, historyid, orderid; timecreated, timemodified
  recompute: local_sentientia_cart_credits per user: balance of the MAX(id) row, its currency, lifetime_earned
  = SUM(credits > 0), lifetime_spent = SUM(-credits < 0) (includes payouts and negative corrections), timemodified
```

### Tenant, status, side effects

- **Tenant:** the buyer's current root (above). An unresolvable path gives 0: visible to cross-tenant
  viewers and the owner only (`SC classes/external/list_orders.php:67-69`; `tenant.php:335-350`); counted.
  Ledger rows inherit through `historyid` (`SC cart_manager.php:740-747`); historyid 0 rows are unscoped.
- **Order status:** all lines 2 -> `paid`; all 3 -> `cancelled` (value went back to the wallet, not the
  card, `BC biz_cart.php:1203-1243`); 2 and 3 -> `part_cancelled` (NEW); only 0/1 -> `abandoned` (NEW;
  BizLMS never showed them, `BC biz_cart_history.php:205,208`); 0/1 mixed with 2/3 -> status from the 2/3
  lines, the 0/1 lines `not_completed` and flagged. **Never** `open` (`get_or_open_cart` adopts it,
  `SC cart_manager.php:55-59`), `pending` or `failed` (`mark_paid` accepts both, :397), `refunded` or
  `partial_refund` (`refund` accepts partial_refund, :511). paygw status is evidence only: `process.php:207`
  writes 1 for failure while `airpay_helper.php:205` writes `ORDER_STATUS_PAID = 1` (defined :48).
- **Side effects:** never `checkout`, `mark_paid`, `mark_failed`, `refund`, `invoicer::issue_for_order` or
  notifier methods; no enrol or unenrol (legacy enrolments came from enrol_fee, `PGW process.php:106-122`);
  no BizLMS events (`checkout_completed` queues an ERPNext POST, `BC classes/observer.php:80-98`); no
  invoice numbers allocated; no BizLMS adhoc tasks run.

### Schema additions

- `local_sentientia_cart_history`: + legacy_source char(20) NULL; status COMMENT adds abandoned|part_cancelled.
- `local_sentientia_cart_ledger`: event_type COMMENT adds the legacy types (no new column).
- `local_sentientia_cart_invoices`: status COMMENT adds legacy_external.
- NEW `local_sentientia_cart_credit_txn`: id; userid NOT NULL; costcenterid NOT NULL DEFAULT 0; event_type
  char(30); amount, balance_after number(14,2); currency char(3) DEFAULT 'INR'; historyid, orderid,
  ledgerid NULL; initiatedby NOT NULL DEFAULT 0; reason text NULL; timecreated, timemodified. FK userid;
  indexes (userid, timecreated), costcenterid.
- The map's `legacy_ref` columns and UNIQUE legacy indexes are not built (R6).
- lang en + hi: status_abandoned, status_part_cancelled (`return.php:37` builds 'status_'.$status; en
  :57-62 and hi :62-67 define six), legacy-invoice notice, privacy strings.

### Code fixes

1. `SC classes/cart_manager.php` `mark_paid` (:388-399), `mark_failed` (:484-490), `refund` (:506-513): refuse
   rows with `legacy_source` set (`error_invalidstate`).
2. `SC classes/external/list_orders.php:55-60`: non-admin owners do not see `abandoned`.
3. `list_orders.php:100-126` and `SC admin_orders.php:31`: LEFT JOIN `{user}` for buyer name and email when
   billing is empty; search them (:83-91).
4. `SC history.php:24-30`: use the keys list_orders returns (orderid, placed_on, total_amount, status).
5. `SC templates/return.mustache:7-60` + `return.php:37-61`: render cancelled, part_cancelled, abandoned,
   refunded, partial_refund, and the per-line status. `SC classes/invoicer.php:168-213` / `invoice.php:30-31`:
   `legacy_external` renders "Issued in ERPNext as <number>"; `invoicer.php:70` refuses prefix 'ERPNEXT'.
6. `SC cart_manager.php:750-765` daily_sums: skip unknown types before creating the bucket; count legacy
   types with their sign as BizLMS did (`BC biz_cart.php:1986-2051`, methods 2 and 9 excluded).
7. `SC classes/privacy/provider.php`: declare and handle `credit_txn`, `initiatedby` and the ledger payload
   ids; stop deleting `local_sentientia_cart_id` rows that a paid order references (:217,:239).
8. An admin (and possibly learner) view of credits: no runtime reader exists today (only the provider,
   `provider.php:68-75,151-166`).

### Fixture

Create the five BC tables and the two PGW tables from their install files; config uniqueidentifier
1000000, globalcurrency INR, itempriceisnet 0. Users `/1/2`, `/77`, `/177/5`, ''. Orders: 1000001 (two
local_courses lines paid, one discount 100.00, one tax 17.994; two paygw_airpay attempts, one failed and
one status 2; enrolmentlog rows; no ledger rows); 1000002 (cancelled with sale and cancel ledger rows and a
+500 credit); 1000003 (mixed 2/3); 1000004 (status 0); 1000005 (status 1); a NULL-identifier line; an
identifier with two userids (abort); a user with INR and EUR credits (skip); credits used, payout,
correction and two cash-drawer ledger rows; an invoice for 1000001 and one for the aborted identifier
(skipped); `local_biz_cart_id` up to 9. Assert statuses (none open, pending or failed), tenants, paise
totals, items_json, orderid = identifier, gateway_ref from the status-2 attempt, timepaid not 0, ledger
types, `ERPNEXT-` invoice and no AIRPAY- invoice, balances, list_orders and get_order pass
`clean_returnvalue`, daily_sums, `mark_paid`/`mark_failed`/`refund` throw on imported rows, a new checkout
gets orderid above 1000009, second run no-op, empty sinks.

### Verification corrections applied

- The "ME tree lacks amd/" claim is refuted: both trees have `amd/src` and `amd/build`. That half of the
  history fix and the drift risk are removed; the `history.php` key mismatch remains.
- `timepaid` never uses `paygw_airpay.timemodified` (always 0).
- One identifier can have several paygw attempts: pick status 2, latest id; others to payload.
- paygw status 1 is ambiguous (two writers).
- Currency: `get_config` returns false, stored as ''; the ''->INR rule covers it.
- Invoices without a target order are skipped and reported.
- NULL-identifier orders get an order number (they could not be opened otherwise).
- Citation: history table is `SC db/install.xml:36-80`.
- rebookitem lines documented.

### Open questions

Answered 2026-10-07 (decision F-02), from the April copy and the signed keys; re-read at Stage B:

- I-20 counts and production `config_plugins local_biz_cart` (uniqueidentifier, itempriceisnet, enabletax,
  globalcurrency, invoicingplatform): the config holds only `accountid`, `expirationtime`, `globalcurrency` = INR,
  `maxitems` and `version`; `uniqueidentifier` and `itempriceisnet` are unset (the importer warns and treats them as base 0
  and gross; identifiers 1..5 are consistent with base 0). Five orders in fifteen months, all by Public learners: one paid
  (Rs 10, 10 January 2025), four abandoned (Rs 2,197 attempted); none charged GST; every line INR; 0 NULL-identifier lines,
  0 mixed buyers, 0 orphan users.
- Synthesize the missing ledger payment rows? No (signed `cart.synthesize_ledger = false`).
- Order tenant: the buyer's current path (signed `cart.order_tenant = buyer`).
- Abandoned checkouts: admin-only orders (signed `cart.abandoned = admin_only`; April: 4, all Public).
- Legacy credit balances: honour, pay out, or write off? Who owns the liability? Frozen history, a Finance question
  (`cart.credit_balances = frozen_pending_finance`; decisions block below). April: none.
- May admins refund imported orders? No (signed `cart.admin_refund_imported_orders = false`).
- Are the ERPNext invoices the legal tax invoices; link out to ERPNext? Reference only, no link-out, a Finance question
  (`cart.erpnext_invoices_legal = reference_only_pending_finance`). April: no stored invoice numbers, ERPNext never connected.
- Is `paygw_airpay` deployed on 5.2; is the PayPal gateway used for any cart order? Yes, the code is at
  `moodle-enhancement/payment/gateway/airpay` (also in the rehearsal codebase and XAMPP; the April config has `paygw_airpay`
  version 2024100700.1). PayPal was never configured: April `payment_gateways` has only airpay (enabled) and the core
  `payments` table has 0 rows.
- Cash-drawer rows with historyid 0: imported for cross-tenant admins only (signed `cart.cash_drawer_rows_without_order =
  import_admin_only`).
- [CONFIRM] Delete stale `\local_biz_cart\task\*` rows from `task_adhoc` before cutover? No: the April copy has 0
  `local_biz_cart` task_adhoc rows and the signed `cart.stale_task_adhoc_rows = do_not_delete_here` stands.

### Decisions of 2026-10-07

Nitin delegated these on 2026-10-07 ("self review and decide recommended option"). **Airpay Finance was NOT consulted.**
`accepted` on the two finance keys records the delegated recommendation, not a Finance sign-off, and the Finance
questions stay open (`OWNER-DECISIONS-2026-10-07.md`, "Finance explainer").

- **cart.finance_keys_status:** the two keys that were `finance-confirm` are `accepted` in the signed file and the cart
  importer DECLARES them (`importer.php decisions()`, allowed values `frozen_pending_finance` and
  `reference_only_pending_finance`). The framework mechanism that blocks a declared key carried as `finance-confirm` stays
  (covered by the toy sample, not by the signed file). No schema change. The hash is not pinned yet, so the edit is not a
  re-approval event.
- **cart.credit_balances = frozen_pending_finance:** the credit journal and balances import as frozen, admin-only history
  behind `sentientia.cart.imported_credits.enabled` (default OFF). Nothing in Sentientia honours, spends, pays out or writes
  off a balance: native checkout has no reader or writer of `local_sentientia_cart_credits` or `_credit_txn`. April: 0 credit
  bookings, 0 ledger rows, 0 holders, INR 0. If Stage B shows a non-zero balance, the count, the total in INR and the tenant
  go to Airpay Finance before cutover, including how a holder's erasure request is handled: the cart privacy provider deletes
  a holder's balance row on erasure and anonymises the journal, so a balance owed would lose its holder (F-04: recorded as a
  question for the legacy-table privacy ADR; no code now; a Finance answer is a re-approval event).
- **cart.erpnext_invoices_legal = reference_only_pending_finance:** stored ERPNext numbers import as references only
  (`ERPNEXT-<id>`, status `legacy_external`), shown to order admins as 'Issued in ERPNext as <number>' behind
  `sentientia.cart.imported_orders.enabled` (default OFF), with no link-out. Sentientia never issues an invoice number for a
  BizLMS sale. April: 0 invoice rows, no ERPNext connector configured; the one Rs 10 completed sale (10 January 2025, Public
  tenant, no GST) has no tax invoice anywhere.
- **cart.accepted_reasons:** none now. None of the seven cart needs-owner reasons fires on April, and a pre-acceptance would
  pass any count. After Stage B, Nitin reviews each `cart:<code>` with a count above 0 and adds it to `accepted_reasons` (a
  re-approval event).
- **cart.order_number_floor:** a plugin setting honoured at checkout plus the runtime placeholder (column map above). Stage B
  check: after import `bizlms_order_floor` equals the highest imported order number and one rehearsal-only native test order
  gets a number above it.
- **cart.credit_sale_classification = keep_unclassified:** a credit booking that matches a sale ledger row has no class in
  this document; the importer does not guess (R8), keeps `legacy_unclassified` and reports it. April has 0 credit rows. If the
  report's `credit_unclassified` warning count is above 0 after Stage B, revisit with the real rows (`credits_step.php::EVENT_OF`,
  both trees).
- **cart.price_source = enrol_fee_authoritative (confirmed revenue hole; ship before the Stage B persona pass and at the
  latest before cutover):** production sells through `enrol_fee` plus `paygw_airpay` (the cart's own set-price tool already
  names `enrol_fee` the single source of truth), but `commerce::get_course_price()` reads only the catalogue setting
  `course_price_<id>`, so a course priced only through an enabled `enrol_fee` instance reads as Free, `add_to_cart` stores it
  as free, and the basket's `enrollfree` action (no flag, any logged-in non-guest) enrols it through `enrolment::enrol_now()`,
  whose 'never enrol into a paid course' re-check uses the same function. April: 66 courses (61 Public, 1 ZEEA, 4 Airpay; INR
  100-499; all visible) have an enabled priced fee instance and there are 0 catalogue price settings. Fix, both trees:
  `get_course_price()` returns the enabled `enrol_fee` cost and currency when it is > 0, else the catalogue setting;
  `enrol_now()` also returns false when `\local_sentientia_cart\cart_manager::get_course_price($courseid)` is not null
  (guarded by `class_exists`); PHPUnit; catalogue version bump; visual evidence of the catalogue price and the basket
  (desktop and 590 px). `storefront_checkout` stays OFF. F-00: check UAT for any Public test account that has used the basket
  action.
- **cart.withheld_line_refund = state_amounts:** when a paid native order contains a course the buyer may no longer buy, the
  enrolment is withheld and the notes text (`cart_manager::mark_paid()`) and the admin message (`notifier::order_paid()`) state
  the per-line amount to refund; nothing is refunded automatically (an automatic refund is the irreversible option). en and
  hi strings, a test in `purchase_gate_test.php`, a cart version bump; ship before the native cart takes real paid orders.
- **cart.native_tax_invoices = hold:** Sentientia issues no GST tax invoice to a real buyer until Airpay Finance answers six
  points: (1) Sentientia, not Finance's own system, issues tax invoices for LMS course sales; (2) the GSTIN for
  `local_sentientia_cart/our_gstn`; (3) the `AIRPAY-YYYY-NNNN` series (16 characters, restarting each January); (4) how refunds
  get a GST credit note, since Sentientia issues none; (5) whether B2B invoices need an e-invoice IRN; (6) how long issued
  invoices must be kept unredacted, because an erasure request currently blanks the buyer's name, e-mail, phone, address and
  GSTIN on native invoices and order history (CGST Act s.36 retention against DPDP erasure; once Sentientia issues invoices
  this could destroy a record Finance must keep). Until then, which tenants should `local_sentientia_cart/enabled_tenants`
  open at cutover? The default is '77,177'. When Finance answers, `redact_for_user()` may change to keep issued invoices
  unredacted for the retention period.
- **Follow-ups:** F-01 (this ADR and the cart README and state card rewritten to the accepted keys), F-03 and F-87 (before any
  dev or UAT copy is built from an imported database: extend `mask_pii_for_dev.php` to null the new credit and ledger free-text
  reasons, zero `initiatedby` and strip `userid` and `usermodified` from `payload_json`; both trees), F-05 (the Stage B cart
  checks, in the runbook), F-06 (visual evidence on the UAT build for `credits.php`, the 'Issued in ERPNext as' invoice view,
  `return.php` and `history.php` status rendering, the admin_orders Staff notes column and the checkout error path; both
  imported-history flags stay OFF until Nitin reviews them), F-07 (before the native cart takes real paid orders: move the
  notifier subjects and bodies to lang strings, en and hi, and build each recipient's message in the recipient's own
  language), F-08 (the four cart PHPUnit suites added by the import and the ADR-031 gate have never run; run them in CI after
  the finance-keys change, which needs a PHPUnit re-init for the cart version), F-09 (after Stage B: `legacy_reader::fetch_by`
  and the person-column privacy assertion in `importer_contract`).

---

## 14. skills

**Owner:** `local_sentientia_skills` (it also owns the new `local_sentientia_course_levels`; the map
suggested `sentientia_courses`, but the skills importer must not write another plugin's table).
**Depends:** recompletion. **Atomic:** no.

Short names: `SR` = `BZ local/skillrepository/`; `SS` = `SE local/sentientia_skills/`.

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_course_levels (`SR db/install.xml:50-66`; costcenterid `SR db/upgrade.php:38-43`, sortorder :46-51) | NEW local_sentientia_course_levels | P | map; UNIQUE code |
| local_skill_categories (`SR db/install.xml:28-49`) | local_sentientia_skill_cats (`SS db/install.xml:6-18`) | M | map; exact case-insensitive name match to a seeded category -> `merged` into it |
| local_skill (`SR db/install.xml:7-27`) | local_sentientia_skills (`SS db/install.xml:20-39`) | M | map; never auto-merged into the 48 seeded skills (`SS db/install.php:36-116`) |
| `#course.open_skill` (derived from course rows, `BZ local/courses/db/install.php:57-67`) | local_sentientia_course_skills (`SS db/install.xml:61-80`) | M | map, sourceid = course id; natural UNIQUE (courseid, skillid) (:78) |
| `#course_completions.skill` (derived: completions of skill-mapped courses, plus the recompletion archive if decided) | local_sentientia_user_skills (`SS db/install.xml:105-128`) + local_sentientia_user_skill_hist (:130-163) | M | map, sourceid = course_completions id (or archive row id), subkey `hist` for the history row |
| local_interested_skills (`SR db/install.xml:93-107`; TEXT column `SR db/upgrade.php:136`) | NEW local_sentientia_skill_interest | M | map, sourceid = the row BizLMS read (lowest id per usercreated), subkey `skill:<id>` per skill |
| local_skillmatrix (`SR db/install.xml:67-91`) | none | | declined as not-history unless Nitin decides (see below) |

### Column maps

```
local_course_levels -> local_sentientia_course_levels
  id -> id (preserve; course.open_level)   name -> name   code -> code (pre-check: duplicates or empty codes are
                                   reported and block the UNIQUE index; the form's empty checks write to $error, not
                                   $errors, SR classes/form/levelsform.php:55-60; uniqueness is only a lookup, :61-65)
  open_path -> open_path (tenant rule); costcenterid -> fallback (SR classes/local/querylib.php:21)
  sortorder -> sortorder; (decision) -> proficiency 1..5 (operator CSV; name heuristic prefill:
     awareness 1, basic/beginner/foundation 2, intermediate 3, advanced 4, expert 5, else 1)
     2026-10-07 decision LRN-07: the csv is FILLED: 1,2;2,3;3,4;4,5;5,1;7,2;8,3;9,4;10,5;11,2;12,3;13,4;14,5;15,1;16,2;17,3;18,4
     (level id, proficiency; the 17 April levels by the rule above; levels 5 and 15 match no rule word and take the default
     1; level 16, the plural of 'basic', is reviewed to 2)
  usercreated, usermodified -> not copied (keeps the provider's "catalogue holds no user data" claim,
     SS classes/privacy/provider.php:20-22)
  timecreated, timemodified -> same; finalise(): reset_sequence

local_skill_categories -> skill_cats
  name -> name (trim; widen target to 255, SS install.xml:10); shortname -> idnumber (NEW; globally unique in BizLMS,
     SR classes/form/skill_category_form.php:62-65); open_path -> open_path (NEW; tenant rule); costcenterid -> fallback;
  sortorder (char) -> sort_order (int cast); parentid, depth, path -> not copied (never written,
     SR classes/event/insertcategory.php:54-58; report parentid > 0); timecreated; description '', icon 'fa-cogs',
     color '#0066A7' (SS install.xml:12-13)
  plus one fallback category 'Imported - uncategorised' (open_path NULL) for orphan skills (deletion did not check
     for skills, SR ajax.php:85-88; gap analysis INNER JOINs categories, SS classes/skills_manager.php:291)

local_skill -> skills
  category -> categoryid (map; unmapped -> fallback category); name -> name (widen to 255, SS install.xml:25);
  shortname -> idnumber (NEW); description -> description (editor HTML, SR classes/external.php:59; rendered FORMAT_HTML,
  SS view.php:159; embedded draft-file images are dead: report them); open_path -> open_path (NEW); costcenterid -> fallback;
  max_level 5; sort_order 0; parentid, usercreated, usermodified, timemodified -> not copied; timecreated

course.open_skill / open_level -> course_skills
  course.id -> courseid; open_skill -> skillid (map; skip 0, NULL, SITEID and dangling ids: the external delete did not
  reset course.open_skill, SR classes/external.php:259-262); open_level -> teaches_level = course_levels.proficiency
  (1 when 0 or unmapped; clamp to max_level, SS skills_manager.php:1173); timecreated = course.timecreated

completions -> user_skills and user_skill_hist (one chronological pass per user and skill)
  user_skills: userid, skillid, current_level = MAX(teaches_level) over completed courses (never downgrade,
     skills_manager.php:400-401); source 'import'; source_id = course of the completion that set the max;
     timecreated = MIN(timecompleted); timemodified = timecompleted of the grant that set current_level
  user_skill_hist: previous_level (0 for the first), new_level, source 'import', source_id = course id,
     changed_by_userid NULL, timecreated = timecompleted; a completion that does not raise the level writes no row
  timecompleted NULL -> skip; deleted users -> skip (Sentientia never shows them, skills_manager.php:59); suspended -> import
  a native row (source course/self/manual) already present: never overwritten downward, but the missing historic
     hist rows are still inserted and user_skills.timecreated is lowered to MIN(timecompleted)

local_interested_skills -> skill_interest
  take the lowest-id row per usercreated (BizLMS read one row with get_record, BZ blocks/suggested_courses/block_suggested_courses.php:97;
     BZ blocks/suggested_courses/classes/plugin.php:53); report the other rows
  usercreated -> userid; interested_skill_ids CSV -> one row per skill (explode, trim, int > 0, must be mapped; the rest
     reported: the value is spliced raw into SQL, plugin.php:62); timecreated; timemodified (0 -> timecreated)
  active 0 -> skip (withdrawn); open_costcenterid -> not copied, but counted in preflight as a tenant cross-check
  usermodified -> not copied
```

### Tenant, status, side effects

- **Tenant:** catalogue rows: legacy `open_path` when it matches `^/\d+(/\d+)*$` and its root passes
  `assert_valid`; `'0'` or `''` (the upgrade default, `SR db/upgrade.php:118`) -> `'/' . costcenterid` when
  > 0; else NULL (shared platform catalogue, today's meaning, `SS skills_manager.php:29-31`) and reported.
  BizLMS already hid `'0'` rows (inner join, `SR lib.php:317`). User rows have no tenant; readers scope by
  `user.open_path` (`skills_manager.php:50-63,71-80`).
- **Status:** BizLMS "achieved" is derived: `timecompleted` NOT NULL (`SR renderer.php:281`).
- **Side effects:** no `course_completed`; never `update_from_course`, `record_skill_change`,
  `save_course_skill` or `self_rate_skill` (they stamp `time()`, `skills_manager.php:406,422,476,556,1188`).
  **Cron must be off:** once `course_skills` rows exist, any `course_completed` event runs
  `update_from_course` (`skills_manager.php:387-433`) and writes a native row stamped `time()`. The
  course-skill and completion steps run in the same feature window. No enrolments, messages, points,
  AI calls (`SE local/sentientia_skillsai/classes/task/rebuild_gap_feed.php:35` stays flag-gated).

### Schema additions

- `skill_cats` and `skills`: + idnumber CHAR255 NULL (index), + open_path CHAR255 NULL; name widened to 255;
  raise `edit_category.php:26` and `edit_skill.php:27` maxlengths.
- NEW `local_sentientia_course_levels`: id (preserved), name CHAR255, code CHAR255 UNIQUE, open_path, proficiency
  INT(2) NULL, sortorder INT NULL, timecreated, timemodified.
- NEW `local_sentientia_skill_interest`: id, userid FK, skillid FK, timecreated, timemodified; UNIQUE (userid, skillid).
  Declare it in the provider.
- The map's shared `local_sentientia_import_map` is the framework map. Upgrade steps above 2026092501; reconcile
  first: the 2026061700 repaint step exists only in `TOP local/sentientia_skills/db/upgrade.php:73-102`.

### Code fixes

1. `SE local/sentientia_catalog/classes/catalog_manager.php:471-473`: level label from
   `local_sentientia_course_levels.name` by `course.open_level`, not the hard-coded 1-3 map.
2. `SE local/sentientia_users/skillprofile.php:126-132`: scope recommendations with
   `skills_manager::course_read_scope_sql`, exclude completed courses, drop the raw LIMIT. (The leak is live
   only once `role_skills` rows exist, :61-67,105,123-132; still ship it.)
3. `SS view.php:70-76` + `templates/view.mustache:116`: use columns `level`/`label` (`SS install.xml:87-89`).
4. `SS index.php:61-68`: read the `get_gap_courses()` arrays by `fullname`, `courseid`, `skill_name`
   (`skills_manager.php:728-735`), and either add `teaches_level` to that array (the SELECT fetches it, :715)
   or drop the label read at :66. Show held skills when the designation has no role skills.
5. `view.mustache:37`: source codes (course/self/manual/import) as lang strings, en + hi.
6. Privacy: declare `skill_interest`.
7. A reader for interests (profile chips and the skills-recs rail behind `sentientia.dashboard.skillsrecs.enabled`).
8. If Nitin scopes the catalogue: filter `open_path IS NULL OR` descendant in `SS classes/external/list_skills.php:43`,
   `skills_manager.php:760-769`, `SS view.php:29` and `SE local/sentientia_api/classes/external/v1/list_skills.php:65-71`.
9. `SE local/sentientia_org/cli/disable_bizlms.php:70` claims "Merged into sentientia_skills": gate the message
   on the completion marker.

### local_skillmatrix

Declined as not-history unless Nitin decides otherwise. No writer exists in skillrepository (only index
steps, `SR db/upgrade.php:57-95`), and BizLMS showed it only when the positions plugin directory existed
(`BZ local/users/classes/local/user.php:115-121`), which is absent from the snapshot. The table
`local_positions` probably exists on production (an unguarded index step, `BZ local/users/db/upgrade.php:67,107-111`).
If Nitin wants it: `positionid` -> `local_positions.name` -> `role_skills.designation`; `skilllevel` (a course
level id, `user.php:182-192`) -> `required_level` via proficiency; conflicting names across tenants are
reported and not imported.

### Fixture

`\local_sentientia_platform\phpunit\open_path_fixture_trait` (user and course open_path, course
open_level/open_skill; `SE local/sentientia_platform/classes/phpunit/open_path_fixture_trait.php:78-136`)
**plus** the `bizlms_fixture` trait for `user.open_designation` (`bizlms_fixture.php:48`). Legacy tables with
the production-only costcenterid columns and path/depth on categories. Seeds present. Users `/1`, `/177`,
deleted, no path. Categories `/1`, `/177`, `'0'` with costcenterid 77 (-> `/77`), `'0'` with none (NULL,
reported); a skill with a missing category; a 150-character name. Levels ids 5 and 9 (map 9 -> 3, 5 -> 2), one
empty code (reported). Courses c1 (s1, level 9), c2 (s1, level 5), c3 (/177, s2), c4 (dangling 999), c5 (0).
Completions u1 c2 at T1, u1 c1 at T2, u1 in progress, deleted user, u2 c3. Interests: two rows for u1 (lowest
id wins), ids `'3, 7,999,,x'`; an active 0 row. Assert u1 level 3 source import timecreated T1 timemodified T2;
two hist rows; nothing for in-progress or deleted; no mapping for c4/c5; second run adds nothing; no events,
messages or emails; catalog label reads the imported level name.

### Verification corrections applied

- Interests: the lowest-id row per learner, not a union.
- `open_costcenterid` counted as a cross-check.
- The step-4/step-5 race closed (cron off, one window, missing hist rows still written).
- `index.php` fix includes `teaches_level`.
- Level code duplicates and empties pre-checked before UNIQUE(code); `reset_sequence` after the
  id-preserving import.
- `local_positions` treated as likely present; skillmatrix gated on counts and resolution, declined by default.
- Preflight reads `path` and `depth` columns (install.xml declares `path` NOT NULL with no default, which
  `insertcategory.php:54-58` never sets, so production differs).
- skillprofile risk narrowed.
- Fixture adds `open_designation`.
- `local_course_levels.usermodified` dropped explicitly.

### Open questions

- One shared catalogue (ADR-031 status quo) or tenant-scoped readers?
- Merge policy: categories by exact name (proposed), skills never. Keep the invented 48-skill seed on
  production at all? (Deleting seed rows is destructive: [CONFIRM].)
- Approve the level-to-proficiency map (17 local rows; production count I-20). Decided 2026-10-07 (LRN-07): see the
  decisions block below.
- `user_skills.source` for migrated rows: 'import' (proposed) or 'course'?
- Grant skill history from the recompletion archive too, or only from current completions?
- Import `local_skillmatrix`?
- Interests: build a consumer, or import for the record only?

### Decisions of 2026-10-07

- **LRN-07 (level-to-proficiency map; blocks Stage B):** `skills.level_proficiency.csv` was null, so the skills feature was
  blocked at preflight, and `learningplan`, which depends on skills, could not run either (2,071 learning-plan enrolments wait on
  it). The signed rule is applied exactly, plus the review step the signed text itself requires, to the 17 April course levels:
  `1,2;2,3;3,4;4,5;5,1;7,2;8,3;9,4;10,5;11,2;12,3;13,4;14,5;15,1;16,2;17,3;18,4`. Every entry equals what
  `level_map::suggest()` returns except level 16, reviewed to 2. A default of 1 for the non-levelled labels (5 and 15) never
  overstates a learner's proficiency. April courses use levels 1-5, 7-10, 16 and 17; levels 11-15 sit under root 80, which is
  not a registered tenant, and no course uses them. Preflight `preflight_level_map()` adds the warning
  `level_proficiency_differs_from_rule:<ids>` whenever a csv entry differs from the rule. Stage B: re-run `--preflight` for
  skills on the live backup and update the csv before the hash is pinned if a level is new or renamed. The test that expects
  the signed file to block the feature until the map is filled skips itself.

---

## 15. classroom

**Owner:** `local_sentientia_classroom` (also changes `local_sentientia_pages`, `local_sentientia_calendar`,
`blocks/sentientia_trainer`, `theme/airpayux`). **Depends:** org. **Atomic:** yes.

Short names: `CL` = `BZ local/classroom/`; `LO` = `BZ local/location/`; `SC2` = `SE local/sentientia_classroom/`;
`sm` = `SC2 classes/session_manager.php`; `wm` = `SC2 classes/waitlist_manager.php`.

### Sources and targets

| Source | Target | Id | Key / notes |
|---|---|---|---|
| local_location_institutes (`LO db/install.xml:6-26`; address TEXT via `LO db/upgrade.php:40-45`) | local_sentientia_locations (`SC2 db/install.xml:141-167`), institute-level row | M | map (institutes and rooms share one table, so ids cannot be kept) |
| local_location_room (`LO db/install.xml:27-48`) | local_sentientia_locations, child row | M | map |
| local_classroom (`CL db/install.xml:6-85`; certificateid via `CL db/install.php:202-211`) | local_sentientia_classroom (`SC2 db/install.xml:5-37`) | P | map; adopt migrate_all header copies (below) |
| local_classroom_trainers (`CL db/install.xml:110-131`) | NEW local_sentientia_classroom_trainers | M | map; natural UNIQUE (classroomid, trainerid) |
| local_classroom_courses (`CL db/install.xml:86-109`) | NEW local_sentientia_classroom_courses | M | map; group (classroomid, courseid), duplicates merged into the lowest id |
| local_classroom_sessions (`CL db/install.xml:156-193`) | local_sentientia_classroom_sessions (`SC2 db/install.xml:39-68`) | P | map |
| local_classroom_users (`CL db/install.xml:220-252`) | local_sentientia_classroom_users (`SC2 db/install.xml:70-89`) | M | map; group (classroomid, userid) against target UNIQUE (:86-87) |
| local_classroom_attendance (`CL db/install.xml:253-280`) | local_sentientia_classroom_attendance (`SC2 db/install.xml:91-112`) | M | map; group (sessionid, userid) against target UNIQUE (:110) |
| local_classroom_waitlist (`CL db/install.xml:302-322`) | local_sentientia_classroom_waitlist (`SC2 db/install.xml:114-139`) | M | map |
| local_classroom_trainerfb (`CL db/install.xml:132-155`) | none | | archived `submission_marker`: score is always '1' (`CL lib.php:358-365`); the answers are imported by the evaluation feature from `local_evaluation_completed/_value` (`CL lib.php:323-331`) |
| local_classroom_completion (`CL db/install.xml:281-301`) | none | | archived `completion_rule_config`: per-classroom rule configuration (`CL classes/classroom.php:2401-2431,2460-2501`); its outcome is `local_classroom_users.completion_status` |
| local_classroom_test_score (`CL db/install.xml:194-219`) | none | | archived if empty; rows -> blocker `needsowner` (no writer or reader in CL PHP; no userid column) |
| local_classroom_categories (referenced at `CL classes/classroom.php:2524-2531`, declared nowhere) | none | | archived if the table exists |
| `{files}` component `local_classroom`, filearea `classroomlogo` (`CL classes/local/general_lib.php:65`) | `{files}` component `local_sentientia_classroom`, filearea `classroomlogo`, system context, itemid = classroom id | | copy; originals kept |

### Column maps

```
local_location_institutes -> locations (institute row)
  fullname -> name (trim; fit 200, SC2 install.xml:144)       address -> address (SC2 :146)
  '' -> city; 0 -> capacity; NULL -> equipment, latitude, longitude (SC2 :145-152)
  costcenter -> costcenterid (must pass assert_valid; always a top-level org, LO classes/form/instituteform.php:37,50-55,72)
  visible -> active (NULL -> 1)                               institute_type -> venue_type NEW (1 internal, 2 external,
  NULL -> parentid NEW                                         LO instituteform.php:76-77; LO renderer.php:73-76)
  timecreated; timemodified (<= 1 -> timecreated; the source default is '1', LO install.xml:17-18)
  shortnname [sic], usercreated, usermodified -> not copied
local_location_room -> locations (child row)
  name: fit(institute.fullname) . ' - ' . room.name, the institute part truncated so the room name stays whole
        within 200 (locations.name) and 254 (session.location); institute fullname CHAR225 (LO :10), room name CHAR45 (:31)
  building -> building NEW; address (empty -> institute.address); capacity; instituteid -> parentid NEW (map)
  active = room.visible AND institute.visible; costcenterid, venue_type from the institute; description -> not copied
  a room whose institute is missing: parentid NULL, costcenterid 0, reported

local_classroom -> local_sentientia_classroom
  id -> id (preserve)                    name -> name (SC2 :8)              shortname -> shortname NEW
  description -> description (HTML; view.php:92 renders format_text)
  visible -> visible (NULL -> 1)         open_path -> open_path (tenant rule)
  last numeric segment of the path -> costcenterid (the edit form preselects it, SC2 classes/form/edit_classroom.php:81,197,228-241)
  path segment 2 or 0 -> departmentid    costcenter -> not used (it does NOT rebuild an empty or invalid path;
                                         2026-10-07 decision LRN-14)
  instituteid -> locationid (map) and fit(institute.fullname, 254) -> location (view.php:94, list_classrooms.php:127,
                                         ics_builder.php:43-44 show this text)
  MIN(id) row of local_classroom_trainers -> trainerid (view.php:59-65)
  capacity -> capacity (NULL -> 0; 0 = unlimited in BizLMS, CL classes/classroom.php:2759, and in Sentientia, wm:116)
  status -> status (status mapping)
  nomination_startdate, nomination_enddate (the enrolment window, CL classes/form/classroom_form.php:266-271,425-438)
                                         -> startdate, enddate (0 -> NULL; SC2 lang/en:33,35)
  startdate, enddate (run dates, classroom_form.php:189-195,349-360) -> trainingstart, trainingend NEW
  completiondate (0 -> NULL) -> timecompleted NEW            usercreated -> createdby NEW
  timecreated; timemodified (0 -> timecreated)
  not copied (R7): type, course, classroom_type, institute_type, points, open_points, foodcost, travelcost, othercost,
     the cached counters, trainingfeedbackid, training_feedback_score, morethan_capacity_allow, cr_category, usermodified,
     manage_approval, allow_multi_session, allow_waitinglistusers, config, department, subdepartment, classroomlogo itemid,
     every open_* audience column, approvalreqd, selfenrol, certificateid. Source columns are read with get_columns(),
     so renamed live columns (CL db/upgrade.php:170-188) are detected.
  adopt signature (name, timecreated): header copies by migrate_all (SE local/sentientia_org/cli/migrate_all.php:74-92,295)
     are adopted and overwritten; they carry raw BizLMS status (migrate_all.php:86), which the overwrite corrects

local_classroom_trainers -> classroom_trainers: classroomid; trainerid (missing user -> skip); timecreated, timemodified
  feedback_id, feedback_score (reset on feedback update, CL lib.php:369-371), usercreated, usermodified -> not copied
local_classroom_courses -> classroom_courses: classroomid; courseid (0 or missing -> skip); timecreated, timemodified
  (0 -> timecreated); pretestid, posttestid, course_duration, usercreated, usermodified -> not copied

local_classroom_sessions -> sessions
  id -> id (preserve)                   classroomid -> classroomid           name -> title (fit 254; empty is allowed,
                                        list_classroom_sessions.php:94-96 falls back to 'Session on <date>')
  description -> notes                  timestart -> sessiondate AND starttime (as create_session does, sm:570-572)
  timefinish -> endtime                 if timefinish <= timestart and duration > 0: timestart + duration*60 (minutes,
                                        CL attendance.php:123); otherwise keep and report (update_session throws, sm:641-643)
  roomid > 0 -> locationid (room map) and location 'Institute - Room' (fitted as above); else instituteid > 0 -> institute
                                        location; else NULL/'' (ics_builder.php:43-44 falls back to classroom.location)
  trainerid -> trainerid (0 -> NULL)
  messagelink -> meeting_url; recordinglink -> recording_url: the sanitize_url rule (sm:595-613): http(s) only, <= 1024;
                                        'www.' gets 'https://'; anything else (e.g. ftp://, accepted by
                                        CL classes/form/session_form.php:65,70) -> NULL and warning url_sanitised
  timecreated; timemodified (0 -> timecreated)
  onlinesession, datetimeknown, duration, sessiontimezone, attendance_status, moduletype, moduleid, usercreated,
  usermodified, capacity -> not copied. moduleid's column type depends on install history (INT(2) on fresh installs,
  CL install.xml:177; INT(10) after CL upgrade.php:204-207): check SHOW COLUMNS before ever using it.

local_classroom_users -> classroom_users (group classroomid, userid)
  classroomid; userid (missing user -> skip; deleted users imported)
  usercreated -> enrolledby (SC2 :75-76)    timecreated -> timecreated (shown as 'Enrolled', sm:818; view.php:82)
  timemodified (0 -> timecreated)
  completion_status -> completion_status NEW INT(2) default 0 (0 pending, 1 completed, CL lib.php:1361)
  completiondate -> timecompleted NEW, only when completion_status = 1 (BizLMS stamps it on every recompute,
                    CL classes/classroom.php:2503-2506)
  hours -> hours NEW (attended minutes / 60, CL attendance.php:123-135)
  duplicates: max(completion_status), earliest timecompleted among completed rows, max(hours); others merged
  courseid, supervisorid, prefeedback, postfeedback, trainingfeedback, confirmation, attended_sessions, usermodified -> not copied

local_classroom_attendance -> attendance (group sessionid, userid)
  sessionid; userid; status (mapping below); usermodified > 0 ? usermodified : usercreated -> markedby (CL attendance.php:79,99)
  NULL -> notes; timecreated; max(timemodified, timecreated) -> timemodified (shown as 'marked at', sm:968,989-991)
  classroomid -> check only: must equal session.classroomid, else skip and report
  duplicates: present > absent, then latest timemodified; others merged
  superceded, statuscode -> not copied

local_classroom_waitlist -> waitlist
  classroomid, userid, sortorder: NULL on upgraded installs (CL db/upgrade.php:303-305) -> skip and report
  sortorder -> position: for 'waiting' rows dense-renumber 1..N per classroom by (sortorder, id) (renumber_positions,
                                    wm:191-205); otherwise keep sortorder
  enrolstatus -> status (mapping below); timemodified -> promoted_at or removed_at
  reason <- 'Imported from BizLMS: <reason>' for removed rows; timecreated, timemodified
  enroltype, usercreated, usermodified -> not copied
  a waiting place of a deleted learner -> removed; a duplicate waiting place -> merged as dup_waiting_place
     (2026-10-07 decision LRN-14: the two round-1 behaviours of the importer, recorded here)
```

### Tenant rule

`open_path` is the only tenant key for readers (`SC2 classes/external/list_classrooms.php:65-73`;
`SE local/sentientia_org/classes/org_manager.php:242-270`); access by id needs a non-empty path inside the
caller's tenant (`sm:172-188`). Normalise (trim, add leading `/`, strip trailing `/`, collapse `//`); the
root must pass `assert_valid` and the path must exist in `local_sentientia_org`. Else import with open_path NULL
(cross-tenant only, `sm:161-166`) and report. 2026-10-07 decision LRN-14: the earlier sentence 'else rebuild as
`'/' . costcenter`' is dropped. BizLMS's own readers filtered classrooms on `open_path` and only derived the cost centre
from it, so a classroom with an empty path was never in a tenant admin's lists; rebuilding a tenant would widen who sees the
roster (owner rule 3). Signed `classroom.pathless = cross_tenant_only`. April: 0 classrooms. Child rows inherit through the classroom. Learners from other tenants on an imported roster
are hidden from tenant admins (`sm:311-313,801-803,965`); correct under ADR-031, listed in the report.

### Status mapping

- Classroom: BizLMS 0 new, 1 active, 2 hold, 3 cancelled, 4 completed (`CL classes/classroom.php:37-41`;
  `CL lib.php:1162-1167`). Sentientia 0 cancelled, 1 active, 2 completed (`SC2 db/install.xml:20-21`;
  `sm:355-357`). Map 1 -> 1, 3 -> 0, 4 -> 2, **0 -> 5 (draft), 2 -> 6 (on hold)**, with the enum fix. Codes 3
  and 4 are **not** used for the new states: they collide with raw BizLMS values that `migrate_all.php:86`
  may have copied. If Nitin declines the new states: 0 -> 1 and 2 -> 1 (misleading: no reader honours
  `visible`, `list_classrooms.php:56-80`).
- Attendance: BizLMS 1 present, 2 absent (`CL classes/classroom.php:42-43`; `CL attendance.php:73-77,94-98`),
  0 not marked (placeholder, `classroom.php:1127-1134`). Sentientia 0 absent, 1 present, 2 late, 3 excused
  (`SC2 install.xml:96-97`; `sm:865-868`). Map 1 -> 1, 2 -> 0; 0 -> archived `unmarked_placeholder`
  (a missing row already reads as Absent, `sm:967`).
- Waitlist: BizLMS 0 waiting, 1 moved to enrolment (`classroom.php:1305-1307,1354-1356,3116,3166`).
  1 -> `promoted`. 0 and the user already on the roster -> `promoted` ('already enrolled'). 0 on a completed
  or cancelled classroom -> `removed` ('classroom closed before promotion'). 0 on an active, draft or on-hold
  classroom -> `waiting`, **only once the auto_promote status guard (code fix 6) has shipped**; until then,
  draft and on-hold rows map to `removed` as well.

### Side effects to avoid

- Never `change_status()` (fires `classroom_completed` at `sm:476-483`, which the evaluation observer fans out
  as feedback triggers, `SE local/sentientia_evaluation/db/events.php:52-53`;
  `SE local/sentientia_evaluation/classes/evaluation_engine.php:89-107`), `create()`, `create_session()`,
  `enrol_users()` (rejects deleted users and stamps `$USER`, `sm:701-730`), `mark_attendance()` (`sm:887-907`),
  `unenrol_user()` (calls auto_promote, `sm:765-774`) or any `waitlist_manager` method (`wm:108-157,208-228`).
- No course enrolments (the restored `user_enrolments` stay as they are), no completions, certificates,
  messages, calendar events (BizLMS already wrote `{event}` rows, `CL classes/classroom.php:183-206,353`).
- `local_sentientia_evaluation_triggers`, `user_enrolments`, `course_completions` and `tool_certificate_issues`
  counts must be unchanged.

### Schema additions

- `local_sentientia_classroom`: + shortname CHAR225, trainingstart, trainingend, timecompleted, createdby
  (INT NULL). `startdate`/`enddate` stay the enrolment window.
- `local_sentientia_classroom_users`: + completion_status INT(2) NOT NULL DEFAULT 0, timecompleted INT NULL,
  hours INT NULL.
- NEW `local_sentientia_classroom_courses` (id, classroomid, courseid, timecreated, timemodified; UNIQUE
  classroomid, courseid) and `local_sentientia_classroom_trainers` (id, classroomid, trainerid,
  timecreated, timemodified; UNIQUE classroomid, trainerid).
- `local_sentientia_locations`: + parentid INT NULL, venue_type INT(2) NULL, building CHAR225 NULL.
- The map's `local_sentientia_classroom_legacy` archive table is not built (R6, R7).
- Declare the new user columns and tables in `SC2 classes/privacy/provider.php:34-50`.

### Code fixes

1. `SE local/sentientia_pages/qr_scan.php:43-46,56-62` (both trees): load the session from
   `local_sentientia_classroom_sessions`, require a roster row, call `session_manager::mark_attendance()`
   (`sm:876-910`). Today it writes `local_classroom_attendance` without the NOT NULL `classroomid` and
   `usercreated` (`CL db/install.xml:256,262`), so every scan ends in the catch at :69-73.
2. `SE local/sentientia_pages/qr_attendance.php:54-58`: use `session_manager::require_session_access()`
   (`sm:209-214`); the capability at :41 becomes `local/sentientia_classroom:attendance` (`SC2 db/access.php:16-20`).
3. `sm:57-66,89-103,110-134`: remove the legacy fallbacks (R14). `get_sessions()` sorts the legacy table by
   `sessiondate`, which it does not have (`sm:78`).
4. Status enum 5 draft, 6 on hold: `sm:354-357`, the `change_status` whitelist (:460), `SC2 view.php:47-56`,
   `list_classrooms.php:105-106`, `edit_classroom.php:106-110`, `SC2 index.php:32-36`; en + hi strings.
5. `edit_classroom.php:118-119`: accept capacity 0 as unlimited.
6. `wm:108-127`: `auto_promote` promotes only on status 1 classrooms; `wm:114-115`: do not count deleted users.
7. Protect imported history: `unenrol_user` hard-deletes the roster row and every attendance row
   (`sm:743-763`); `delete()` removes attendance, sessions and roster but not waitlist or the new tables
   (:501-530); `delete_session` removes attendance (:652-664). Block these on completed or imported rows
   (or soft-delete) and cascade to the new tables. 2026-10-07 decision LRN-10: a PENDING imported roster row with no
   completion, progress or attendance, on an active classroom, may be unenrolled by an admin; every row that carries
   history stays blocked (section 17).
8. Privacy: `anonymise_data_for_user` deletes the roster row on the premise that it "carries no
   completion" (`SC2 classes/privacy/provider.php:130-135`). Keep roster rows on anonymise (clear free text
   only), as sentientia_programs and sentientia_learningpath do.
9. `SE local/sentientia_calendar/classes/ics_builder.php:186-213`: filter `cl.status IN (1,2)`; draft and
   on-hold classrooms must not reach learners' calendars. It also puts `session.notes` (imported BizLMS
   description HTML) into events: strip to text.
10. `SE blocks/sentientia_trainer/block_sentientia_trainer.php:39-51`: read `local_sentientia_classroom_trainers`,
    not only `classroom.trainerid`, and drop the `local_classroom` fallback.
11. `TOP theme/airpayux/layout/dashboard.php:175,177`: replace the unscoped `count_records_select('local_classroom', '1=1')`
    with `session_manager::count_classrooms($tenantpath)`.
12. Readers for the imported history (behind a default-OFF flag): view.php overview (training dates, linked
    courses, all trainers, logo); roster columns completion_status, timecompleted, hours (`sm:818-823`);
    a learner "My classrooms" page (`:view` grants only manager and editingteacher, `SC2 db/access.php:11-15`).
    Roster and attendance reads add `u.deleted = 0` or a badge (`sm:818-823,966-975`).
13. Make `sanitize_url` (`sm:595-613`) public static so the importer applies the same rule.
14. A `local_sentientia_classroom_pluginfile` callback for `classroomlogo`.

### Fixture

The ten CL tables and two LO tables from checked-in copies, production-only `certificateid`, upgrade-era
nullable waitlist columns. Orgs `/1`, `/1/5`, `/77`, `/177`. Users A (`/1/5`), B (`/77`), deleted D,
trainers T1 and T2, a `/1` tenant admin, course C. Institute I1 (costcenter 1, type 1, fullname 225
characters) with rooms R1 and R2; I2 (77, type 2). Classrooms CR1 status 4 `/1/5` capacity 0; CR2 status 1
`/77`; CR3 status 3; CR4 status 0; CR5 status 2; CR6 `''` with costcenter 177 (expects a NULL path, 2026-10-07 decision LRN-14); CR7 `'1/5/'`; CR8 invalid root;
CR9 pre-written at its id by a migrate_all-style header copy (adopted). classroom_courses CR1->C twice.
Trainers CR1 T1 then T2. Sessions S1 (CR1, R1, `www.zoom.us/j/1`, `ftp://x`), S2 (online), S3 (timefinish <
timestart, duration 90). Users: A completed; B completion 0 with a completiondate (timecompleted NULL);
duplicate A; D. Attendance: A S1 1; B S1 2; A S2 0 (archived); duplicate A S1 2 (present wins); one with a
wrong classroomid. Waitlist: CR2 sortorder 5 and 9 (positions 1, 2); CR1 status 1 and status 0 (removed);
one already on the roster; one with NULL userid (skipped). One trainerfb and one completion row
(archived); test_score empty, and a second test with one row (blocker). A logo file. Assert ids, statuses
(1->1, 3->0, 4->2, 0->5, 2->6), paths, costcenterid, locations and names within 200, URLs, attendance,
dedupe, waitlist, archived outcomes, logo copy, empty sinks, unchanged core counts, second run no-op;
reader checks as the `/1` admin (`list_classrooms` shows CR1 Completed, not CR2; attendance of S1 shows A
Present); the fixed QR path writes Sentientia attendance; `get_sessions()` works.

### Verification corrections applied

- `reset_sequence` after commit (R4); the `migrate_all.php:300-301` precedent has the same flaw.
- The QR rationale for keeping session ids removed (tokens rotate hourly, `qr_attendance.php:61`,
  `qr_scan.php:28-40`); session ids are kept for `{event}` rows and the completion CSV.
- DPDP: roster rows kept on anonymise (code fix 8).
- Actor ids: no JSON archive is built, so none hide there; `enrolledby` and `markedby` are declared columns.
- Protect-history code fix added (code fix 7).
- Orphaned `enrol_classroom` instances (`CL classes/classroom.php:1253,1781-1786,1817,1829-1846`) recorded as gap G6.
- Ratings added as a dependent of the kept ids.
- The calendar ICS reader added (code fix 9).
- New status codes 5 and 6, not 3 and 4.
- auto_promote has no status check: guard added, and draft/on-hold waiting rows map to `removed` until it ships.
- NULL waitlist and completion columns on upgraded installs skipped.
- Location name overflow: the institute part is truncated.
- `moduleid` column type checked before use.
- `tenant::assert_valid` (not its docblock lines).
- One adopt rule for all features (migrate_all header copies are adopted, not refused).
- `classroomlogo` is copied.

### Open questions

- Add draft (5) and on hold (6), or collapse them to active?
- Waiting rows on completed or cancelled classrooms: `removed` (proposed) or `waiting`?
- Unresolvable `open_path`: pathless (cross-tenant only, proposed), or assign by costcenter or creator?
- I-20: counts and SHOW COLUMNS for `local_classroom*` and `local_location_*`; does `local_classroom_categories`
  exist; is `local_classroom_test_score` empty?
- Do finance or audit need classroom costs (`CL db/install.xml:21-23`) as columns? (Proposed: legacy table only.)
- Online sessions: is `moduleid` a course module id or an instance id
  (`CL classes/event/online_sessions_integration.php:123-137`)?
- QR check-in: require the roster and a time window?
- Which surfaces outside the classroom plugin read `local_sentientia_classroom*`? (Not searched in low-CPU mode.)

### Decisions of 2026-10-07

- **IDN-04 (logo copy):** the `classroomlogo` copy made by `file_rehome` in `finalise()` goes through the `copies_files`
  marker (copy-only, insert-only, idempotent, originals never touched, left in place by `--purge-feature`), like the org
  logo (section 3); the run report counts it. `{files}` is watched for every importer without the marker (ADR-032).
- **LRN-14 (tenant of a classroom with no usable path):** stays `cross_tenant_only` as signed; the tenant-rule sentence, the
  column-map line and the fixture expectation above are corrected to match.
- **LRN-15 (co-trainers):** every trainer listed on a classroom (any row of `local_sentientia_classroom_trainers`) may run ALL
  of its sessions (`classroom.cotrainer_sessions = every_session_of_their_classroom`). BizLMS gated attendance only by
  capability, never by a session's trainer; this is narrower than BizLMS (inside the classroom) and inside ADR-031.
- **LRN-16 (trainer erasure):** core erasure releases the trainer (removes the trainer's rows and clears the columns that name
  them) and keeps classes and learner records; DPDP keeps them against the anonymised user (`classroom.trainer_erasure =
  core_release_dpdp_keep`). Learner compliance records are untouched in both. After the classroom and program merges,
  `trainerid` moves into `privacy_coverage_test::USER_COLUMNS` (F-86) and the program branch's `COMPONENT_USER_COLUMNS`
  constant is deleted.
- **LRN-17 (new states in the UI):** Draft (5) and On hold (6) ship UNFLAGGED as part of the enum fix
  (`classroom.new_states_ui = unflagged`): a select that does not list the stored value rewrites it on save, so gating the
  options would lose data, which overrides the flag rule. The UI is admin-only. Visual evidence must include the Draft and
  On hold filter buttons and the status select.
- **LRN-10 (protect imported history):** see section 17.
- **XC-CLS-ENROL (bulk enrolment by audience):** `local/sentientia_classroom:enrol` is declared by no `access.php`, yet it gates
  `bulk_enrol_by_audience.php`, `preview_audience.php`, `bulk_enrol_audience_form.php` and two web services in `services.php`,
  so the page is refused for everyone, site admins included. Decision: the three PHP sites and `services.php` switch to
  `local/sentientia_classroom:manage` (holders: BizLMS classroom managers, role 1, role 9, and role 10 trainers through the
  signed allow-list grant of `local/classroom:manageclassroom`), behind the new default-OFF flag
  `sentientia.classroom.bulk_enrol_audience`, checked on the form page and the two web services; an empty filter is refused
  (the existing 'pick at least one' wording) so no accidental whole-tenant enrolment; the ADR-031 tenant bound is unchanged.
  The `BASELINE` lines for classroom `:enrol` come out of `capability_names_test.php` in both platform trees in the same change
  (F-90). Screenshots before any flip.
- **Open questions above, answered (signed 2026-09-30):** draft and on hold are added (`classroom.status_new_hold = add_5_6`);
  waiting rows on closed classrooms are `removed` (`classroom.waitlist_closed`); an unresolvable `open_path` is pathless
  (`classroom.pathless`); classroom costs stay in the legacy table (`classroom.costs_as_columns`); QR check-in requires the
  roster (`classroom.qr_checkin_requires_roster`) and uses a window from 30 minutes before the session start to 30 minutes
  after its end (merge b59adb58c, `sentientia_pages/qr_scan.php`).

---

## 16. program

**Owner:** `local_sentientia_programs`. **Depends:** org. **Atomic:** yes.

Short names: `PR` = `BZ local/program/`; `program.php` = `PR classes/program.php`; `completion.php` =
`PR classes/local/completion.php`; `renderer.php` = `PR classes/output/renderer.php`; `SP` =
`SE local/sentientia_programs/`; `pm` = `SP classes/program_manager.php`.

Evidence of little live data: `SE local/sentientia_org/cli/disable_bizlms.php:73` says local_program is "Not
actively used", and locally only `local_bcl_cmplt_criteria` survives (14 rows), which fits deleted programs'
orphaned criteria. The importer is still complete: production counts are unknown (I-20).

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_program (`PR db/install.xml:6-60`; certificateid via `PR db/install.php:188-198`) | local_sentientia_programs (`SP db/install.xml:5`, fields at :8-26) | P | map; collision blocks (no "new id where free") |
| local_program_levels (`PR db/install.xml:130-153`) | local_sentientia_programs_levels | M | map |
| local_program_level_courses (`PR db/install.xml:154-184`) | local_sentientia_programs_courses | M | map; group (levelid, courseid) against target UNIQUE (`SP db/install.xml:72`) |
| local_bcl_cmplt_criteria (`PR db/install.xml:185-206`) | fold into levels.completion_rule and courses.mandatory | | map, outcome folded; MIN(id) wins per (programid, levelid) |
| local_bc_completion_criteria (`PR db/install.xml:232-250`) | fold into programs and levels completion_required | | map, folded; MIN(id) wins per programid |
| local_program_users (`PR db/install.xml:61-93`) | local_sentientia_programs_users | M | map; group (programid, userid) against target UNIQUE (`SP db/install.xml:94`) |
| local_bc_level_completions (`PR db/install.xml:207-231`) | NEW local_sentientia_programs_lvlcomp | M | map; only completed rows; natural UNIQUE (levelid, userid) |
| local_program_trainers, local_program_trainerfb (`PR db/install.xml:251-297`) | NEW conditional tables | M | only if rows exist; no writer in PR (only reads and deletes, `program.php:1305`; `PR externallib.php:135-136`) |
| local_program_completions_bk, local_bc_level_comp_bk, local_program_test_score (`PR db/install.xml:94-129,298-316`) | none | | archived if empty; rows -> blocker `needsowner` (writers commented out, `program.php:1663-1667,1677-1681`) |
| `{files}` component `local_program`, filearea `programlogo`, category context (`program.php:70,786-797`) | component `local_sentientia_programs`, filearea `programlogo`, system context, itemid = program id | | copy (consistent with classroom and learningplan) |

### Column maps

```
local_program -> programs
  id -> id (preserve)            name (CHAR225) -> name (CHAR254, trim) (PR :9; SP :8); when name is empty, shortname
                                 -> name with warning name_from_shortname (2026-10-07 decision LRN-12)
  description -> description; descriptionformat 1 (the editor text is stored raw, program.php:73; SP view.php:103 renders HTML)
  open_path -> open_path (normalise, tenant rule) (PR :39; SP :15)
  open_path -> costcenterid = id of the local_sentientia_org row whose path equals it (org ids are preserved),
               else the root segment (create() does the same, pm:339,348-352)
  visible -> visible (PR :12; toggled at PR externallib.php:781,825)       status + visible -> status (mapping below)
  nomination_startdate, nomination_enddate -> startdate, enddate (0 -> NULL; the enrol window,
               PR classes/local/general_lib.php:127-130; SP :19-22)
  startdate, enddate -> not copied (always forced to 0 on save, program.php:71-72)
  (fold) completion_required from local_bc_completion_criteria
  timecreated; timemodified (0 -> timecreated) (PR :47,:49; SP :25-26 NOT NULL)
  every other column (shortname, program_type, points, capacity, audience CSVs, selfenrol, approvalreqd, category,
  skill/level, programlogo itemid, certificateid, feedback ids, usercreated, usermodified, cached counters) -> not copied

local_program_levels -> levels
  programid -> programid (map; orphan -> skip, BizLMS delete leaves levels, PR externallib.php:132-148)
  level (CHAR255) -> name (fit 254)            description -> description
  sortorder = 0-based dense rank by id ASC within the program (BizLMS orders and locks by id: program.php:1627,2076;
              renderer.php:727; program_levels() has no ORDER BY, program.php:870-876; editing overwrites position,
              program.php:1388,1392)
  completion_required: 1 when the program criteria is 'ALL', absent, or levelids empty; for 'AND'/'OR', 1 if the legacy level
              id is in the levelids CSV (program.php:324), else 0
  completion_rule NEW: 'any' if the level's coursetracking is 'OR', else 'all' (ALL, AND, NULL, '')
              (PR classes/form/level_completion_form.php:56-58; empty default ALL, program.php:276-277)
  timecreated; status, counters, usercreated, usermodified, timemodified -> not copied
  EMPTY LEVELS: a level with no valid level course and no completed level-completion row is skipped (reason
  empty_level) and removed from the required set, EVEN if levelids names it (a kept empty level counts as completed,
  pm:629-632), unless code fix 3 ships first. Auto-create makes 7 levels per program (program.php:1408-1421).

local_program_level_courses -> courses
  levelid -> levelid (map)       programid -> check only (must equal the level's program; on mismatch trust levelid)
  courseid -> courseid (<= 1 or missing -> skip; Sentientia rejects <= 1, pm:819-827)
  sortorder = 0-based rank by id within the level (position is never written, program.php:815-821; BizLMS ordered by id, :899)
  mandatory: 1 when coursetracking is ALL, NULL or ''; for AND/OR with a non-empty courseids set (CSV joined with ', ',
             program.php:270; trim each item), 1 if the course is in the set, else 0; AND/OR with an empty set -> 1
  timecreated; pretestid, posttestid, feedback, duration, counters, position, usercreated, usermodified -> not copied

local_program_users -> users (group programid, userid)
  programid (map; program gone -> skip); userid (missing -> skip; deleted imported)
  status (mapping below); timecreated -> timecreated (MIN of the group)
  timecompleted: status 2 -> completiondate when > 0 (set once at completion, completion.php:224-226); else
                 MAX(completiondate) of the user's imported level completions; else NULL and reported.
                 NOT timemodified: the level cron bumps it on every level completion (completion.php:311,335)
  usercreated -> enrolledby NEW; timemodified -> timemodified NEW
  levelids (CSV) -> evidence only (cross-checked with local_bc_level_completions)
  currentlevelid: recompute step after level completions: the first level by sortorder with no completed stored level
                 completion (renderer.php:330-335; program.php:1643-1647); completed user -> the last level; status 0 -> NULL
  typeid, supervisorid, feedback flags, confirmation (always 0, program.php:617-623), usermodified -> not copied
  duplicates (non-unique index PR :86; no duplicate check at program.php:611-632): prefer completion_status 1 with
  MAX(completiondate); timecreated MIN; others merged

local_bc_level_completions -> lvlcomp (completion_status = 1 only)
  programid, levelid (map), userid; requires an imported users row for (programid, userid), else skip (unenrol deletes the
  users row but not level rows, program.php:716-717)
  timecompleted: the level's completion date from core course_completions over its criteria courses the user completed:
     coursetracking 'OR' -> MIN(timecompleted) (completed on the first qualifying course); 'ALL'/'AND' -> MAX(timecompleted);
     capped at the stored completiondate when that is > 0 (a course re-completed after a reset is later than the real
     level completion); fallback the stored completiondate. The stored value alone is the last cron recalculation time
     (completion.php:173,285-290,314-316; PR db/tasks.php:24-33).
  bclcids -> completedcourseids TEXT (audit only; its format is inconsistent, completion.php:52,302; program.php:1600-1604)
  source 'bizlms'; timecreated, timemodified; usercreated, usermodified -> not copied
  rows with completion_status 0 -> archived (they carry no completion; admin resets leave exactly this, program.php:1669-1670)

conditional (only if rows): trainers: programid (map), trainerid -> userid, feedback_id -> feedbackid (evaluation map, later),
  feedback_score, timecreated, timemodified, usercreated -> assignedby; trainerfb: bc_trainer_id -> programtrainerid (map),
  programid, trainerid, userid, score, timecreated, timemodified
```

### Tenant rule

Normalise `open_path` to `'/' . trim(path, '/')`; the root must pass `assert_valid`. Empty or bad root ->
the root of the creator's `user.open_path`; else NULL and reported. A pathless program is cross-tenant
only (`tenant.php:380-404`; `pm:76-92`). Children inherit through programid; the roster is limited to the
caller's tenant (`pm:194-196`; `SP classes/external/list_program_users.php:54-62`).
2026-10-07 decision XC-TENANT-GUESS: the creator's-root fallback (signed `program.pathless = creator_root`) guesses a tenant,
which BizLMS never did (its program readers scoped by `open_path` only, `BZ local/program/classes/program.php:403-509`).
Nothing changes today: April's only program has a path (`/77`), so the fallback does not fire. The Stage B report counts rows
by tenant method (path, costcenter, classroom, shared_learner_root, creator_root, pathless) with ids for `creator_root`;
`creator_root > 0` means stop, ask Nitin and re-pin if he changes a value.

### Status mapping

- Program: BizLMS status is 0 (new, set on create, `program.php:43,116`) or 2 (completed, :44; its updater
  has no caller, :939-976); the live switch is `visible` (`program.php:536-541`). visible 1 and status <> 2
  -> 1 Active; visible 0 -> 2 Archived; status 2 -> 2 Archived; other values -> by visible, reported. Never
  0 (Draft): BizLMS had no draft, and status-0 programs were live (`program.php:645,718`).
- Users: completion_status 1 -> 2 Completed (`pm:883`; `list_program_users.php:64-69`); 0 with progress
  evidence (levelids non-empty, a completed level row, or a course completion on a program course) -> 1 In
  progress; else 0 Enrolled. completion_status 1 with completiondate 0 -> 2 with the fallback date, flagged
  (BizLMS lists say not completed, `program.php:552`; `PR classes/local/userdashboard_content.php:23,52`;
  the certificate download says completed, `renderer.php:780-787`).
- Criteria: ALL = every course; AND = every listed course; OR = any listed course
  (`level_completion_form.php:56-58`; `program_completion_form.php:51-53`).

### Side effects to avoid

- Never `program_manager::enrol_users()` (rejects deleted users, forces status 0, `pm:1044-1090`).
- No `course_completed` (observed by the programs observer, `SP db/events.php:14-15`, gamification, xAPI and
  webhooks), no `program_completed` (queues evaluations, `SE local/sentientia_evaluation/db/events.php:41-42`),
  no enrolments, certificates, messages, points (`PR db/install.xml:14,314`), calendar events
  (`program.php:160-250`) or audience enrolment (`SP classes/program_audience_enroller.php:36-80`).

### Schema additions

- `levels`: + completion_rule CHAR(10) NOT NULL DEFAULT 'all'.
- `users`: + enrolledby INT NOT NULL DEFAULT 0, + timemodified INT NOT NULL DEFAULT 0.
- NEW `local_sentientia_programs_lvlcomp`: id, programid, levelid, userid, status INT(2) DEFAULT 0,
  timecompleted NULL, completedcourseids TEXT NULL, source CHAR(20) DEFAULT 'bizlms', timecreated,
  timemodified; UNIQUE (levelid, userid); index (programid, userid).
- Conditional: `local_sentientia_programs_trainers`, `_trainerfb`.
- `legacy_id` and `legacy_json` are not built (R6, R7).

### Code fixes

1. A learner "My programs" surface behind a default-OFF flag (`:view` is manager/editingteacher only,
   `SP db/access.php:4-7`; `view.php:22`, `index.php:12`).
2. Level rule 'any' in `is_level_completed_by_user` (`pm:623-642`).
3. Empty levels must not count as completed (`pm:629-632`; inflates `pm:729,763-764`).
4. Honour program `completion_required = 0` in the observer (`SP classes/observer.php:76-91`).
5. The observer persists status 2 and timecompleted, skips users with no enrolment and users already at 2
   (`observer.php:40-51,63-101`), and never downgrades.
6. `get_user_program_state` (`pm:690-773`) respects `lvlcomp` status 1 and `pu.status = 2`.
7. Roster reads exclude `u.deleted = 1` (`pm:888-895,903-927,1111-1158`).
8. `SP index.php:33-34,59-63`: relabel the status-2 tile "Archived".
9. `list_program_users.php:94-105`: add "Completed on".
10. Protect history: the roster trash action (`list_program_users.php:85-92` -> `pm:1095-1100`) and
    `delete()` (`pm:419-451`) must not hard-delete completions; `delete()` cascades to `lvlcomp`. 2026-10-07 decisions
    LRN-10 and LRN-13: an imported program row, an imported level, and trainer or feedback rows are protected history
    (delete is refused, as delete_level follows the same rule); a roster row with a completion, progress or attendance
    stays blocked, while a pending imported enrolment with none of those may be unenrolled by an admin.
11. Privacy (`SP classes/privacy/provider.php:19-116`): `lvlcomp`, `enrolledby` (anonymise as an actor).
12. `delete_level` (`pm:532-551`) refuses an imported level or a level with a stored completion; unassign
    (`pm:869-874`, `unassign_course_from_level`) is unchanged and cleans up `lvlcomp` (2026-10-07 decision LRN-13).
13. A `programlogo` pluginfile callback and reader.

### Fixture

`\local_sentientia_org\test\bizlms_fixture` trait (`$this->ensure_bizlms_schema()`), the 12 PR tables from
a checked-in copy plus `certificateid`. Orgs 1, 77, 177, 5 (`/1/5`). Users in each tenant, deleted,
suspended, one missing id. Courses with completions (one earlier than the stored level date). P1 visible,
`/1/5`, seven levels (only L0, L1 with courses), program criteria AND with levelids including an empty
level (skipped and removed from the required set), L0 ALL, L1 OR with courseids `'12, 13'`; P2 visible 0;
P3 path `''` with a `/77` creator; an edited level whose position was overwritten (order by id); duplicate
criteria rows (MIN id wins); orphan level and criteria. Users: completed; completion 1 with date 0;
in progress; not started; a duplicate pair; deleted; missing. Level completions: status 1 (OR level: MIN
course date), status 0 (archived), one with no enrolment. One trainers row and one `_bk` row (blockers).
Assert ids, statuses, currentlevelid, dates, mandatory and rules, paths, orphans reported, empty sinks,
unchanged core counts, a native program gets an id above the legacy maximum, second run no-op.

### Verification corrections applied

- Sort order by id ASC, not (position, id).
- Duplicate criteria: MIN(id).
- Level date: MIN for OR, MAX for ALL/AND, capped at the stored date.
- PRESERVE or block; no split id space.
- `tenant::assert_valid`; `reset_sequence` after commit.
- Empty levels named only in levelids are skipped and dropped from the required set.
- `timecompleted` fallback is the level completion date, not `timemodified`.
- Citation paths made explicit.
- Program logos copied.

### Open questions

- I-20 counts for all 12 tables.
- completion_status 1 with date 0: completed (proposed, flagged)?
- Inactive programs: Archived (proposed) or Draft? Should learners see history in inactive programs?
- Empty auto-created levels: skip (proposed)?
- Deleted users' enrolments: import for audit (proposed)?
- Pathless programs: creator's root (proposed) or cross-tenant only?
- Non-empty `_bk` tables: a visible "previous completion" history?
- Certificates: re-link `certificateid` (gap G1)?
- Enrol instances `enrol='program'` (`program.php:1489-1531`; `PR checkenrol.php:33-41`): gap G6.

### Decisions of 2026-10-07

- **IDN-04 (logo copy):** the `programlogo` copy goes through the `copies_files` marker (see section 15 and ADR-032); the
  importer is the fifth caller after org, cohort_scope, learningplan and classroom.
- **LRN-12:** a BizLMS program with no name but with a shortname imports under its shortname with the warning
  `name_from_shortname`, so its levels and learner history are kept (`program.nameless_with_shortname =
  import_under_shortname_with_warning`). The shortname is the program's own BizLMS identifier, not personal data. April's only
  program has a 13-character name.
- **LRN-13:** an imported program level cannot be deleted, even when no learner has a stored completion on it, because it
  defines the completion of imported enrolments (`program.delete_imported_level = blocked`); editing it, unassigning courses
  or archiving the program remain possible. Empty BizLMS levels are already skipped at import (`program.empty_levels =
  skip`), so every imported level carries courses.
- **XC-TENANT-GUESS:** see the tenant rule above.
- **F-58 (with the program merge):** `manage.mustache` labels the status 0 filter 'Cancelled' in hard-coded English although
  status 0 is Draft; replace the button text with the `status_draft` string (en and hi exist) and show it in the visual
  evidence. The two program engine fixes (the observer stores completions only for enrolled learners; an empty level no longer
  counts as completed) ship UNFLAGGED as defect fixes (code fixes 3 to 5); before the UAT deploy, list native programs with an
  empty level (ids only), because those stop showing learners as completed.
- **F-54 (April expectations for the post-Stage-B acceptance list):** `program`: orphan levels and criteria under the missing
  program 1 (`orphan_program`); recompletion and classroom: none (0 rows).
- **Open questions above, answered (signed 2026-09-30):** completed with date 0: completed, flagged; inactive programs:
  Archived, not Draft; learners do not see history in inactive programs; empty auto-created levels: skipped; deleted users'
  enrolments: imported for audit; pathless programs: the creator's root, else none; non-empty `_bk` tables: archived in the
  legacy tables.

---

## 17. learningplan

**Owner:** `local_sentientia_learningpath`. **Depends:** org, skills. **Atomic:** yes.

Short names: `LP` = `BZ local/learningplan/`; `lib/lib.php` = `LP classes/lib/lib.php`; `view.php` =
`LP classes/render/view.php`; `SL` = `SE local/sentientia_learningpath/`; `ptm` = `SL classes/path_manager.php`.
`local_learningplan_approval` is owned by the **request** feature (section 19).

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_learningplan (`LP db/install.xml:6-51`; production-only costcenter, department, subdepartment, subsubdepartment, certificateid: `LP db/upgrade.php:139-143,186-214`, `LP db/install.php:170-176`) | local_sentientia_learningpath (`SL db/install.xml:5-39`) | P | map; adopt migrate_all header copies (signature name) |
| local_learningplan_courses (`LP db/install.xml:53-75`; moduletype, instance from `LP db/upgrade.php:83-94`) | local_sentientia_learningpath_courses (`SL db/install.xml:41-64`) | M | map; group (planid, courseid) against target UNIQUE (:62) |
| local_learningplan_user (`LP db/install.xml:77-97`) | local_sentientia_learningpath_users (`SL db/install.xml:66-85`) | M | map; group (planid, userid) against target UNIQUE (:82) |
| local_plan_course_status (`LP db/install.xml:99-122`) | NEW local_sentientia_lp_course_status | M | only if rows (no writer or reader in LP; expected empty) |
| `{files}` component `local_learningplan`, filearea `summaryfile` (`lib/lib.php:38,128`; served by `LP lib.php:137-161`) | component `local_sentientia_learningpath`, filearea `summaryfile`, itemid = pathid, system context | | pathnamehash; originals kept |

### Column maps

```
local_learningplan -> learningpath
  id -> id (preserve)                     name (CHAR255) -> name (widen target to 255, SL :8)
  shortname -> shortname NEW              description -> description (editor HTML, lib/lib.php:67-68); descriptionformat 1
  objective -> objective NEW              visible -> status (1 -> 1 active; 0/NULL -> 0 archived) and visible
                                          (lib/lib.php:263-272; SL :16-18)
  startdate, enddate -> startdate, enddate (0 -> NULL; display-only in BizLMS, view.php:614-623)
  open_path -> open_path (widen to 255; tenant rule)
  last numeric segment -> costcenterid (org id, the edit form's meaning, SL classes/form/edit_path.php:57,199-207;
                          ptm:399-403); not an org -> 0 and reported (the form offers only visible orgs)
  second segment -> departmentid (depth >= 2)
  approvalreqd -> approvalreqd NEW (NULL -> 0; LP classes/forms/learningplan.php:184-189)
  selfenrol -> selfenrol NEW (LP classes/output/search.php:59)
  lpsequence -> sequential NEW (forced 1 by the form, classes/forms/learningplan.php:171-173; locking view.php:2211-2219)
  learning_type -> learning_type NEW (1 core, 2 elective, view.php:2572-2576)
  open_points -> points NEW (stored only; shown as credits, view.php:746,3022)
  open_categoryid -> categoryid NEW; open_skill -> skillid NEW (skills map; 0 -> NULL);
  open_level -> levelid NEW (local_sentientia_course_levels, id preserved; 0 -> NULL)
  certificateid -> certificateid NEW (stored only)
  usercreated -> usercreated NEW (the creator is shown, view.php:629-635); usermodified -> usermodified NEW
  timecreated; timemodified (0 -> timecreated; create never sets it, lib/lib.php:31-33)
  adaptive_mode 0; score_threshold_low/high NULL (keeps the journey engine off, SL classes/adaptive/journey_engine.php:63-64,105-106)
  open_band, open_branch, open_group, open_hrmsrole, open_designation, open_location, open_states, open_district,
  open_subdistrict, open_village and the production-only org columns -> not copied (R7; the map's audience_json dropped)

local_learningplan_courses -> courses (group planid, courseid)
  planid -> pathid (= the kept id, resolved through the map)
  courseid -> courseid (NULL or missing -> skip; the reader inner-joins course, ptm:271-277)
  sortorder -> sortorder: NULL -> append after the max in legacy id order; then dense 0..n-1 per path
  nextsetoperator -> mandatory: lower(trim) 'and' -> 1, else ('or', NULL) -> 0 (lib/lib.php:1006-1010; view.php:2484-2485;
                     default 'or', lib/lib.php:1033)
  moduletype, instance -> dropped when '' or 'course' (writer commented out, lib/lib.php:1031-1032); other values -> blocker
  timecreated (0 -> the path's timecreated; renumbering rewrote it with time(), lib/lib.php:370) -> timecreated
  timemodified -> timemodified NEW; usercreated -> usercreated NEW; usermodified -> usermodified NEW
  is_remedial 0, is_accelerator 0, remedial_for_courseid NULL
  duplicates: keep the lowest non-NULL sortorder, then the lowest id; others merged

local_learningplan_user -> users (group planid, userid)
  planid -> pathid; userid (missing -> skip; deleted imported; readers hide them, ptm:891)
  status + completiondate -> status (mapping below); completiondate -> timecompleted only when status = 1 (0/'' -> NULL)
  startdate -> NOT copied. Preflight BLOCKS (`user_startdate_has_no_target_column:N`) if production has any value (none
               expected: nothing writes it, LP ajax.php:60-66,76-82; April: 0 of 2,071). 2026-10-07 decision LRN-09,
               `learningplan.user_startdate = block_if_present`
  timecreated -> timecreated (the enrolment date, view.php:2026); timemodified -> timemodified NEW
  usercreated -> enrolledby NEW (equal to userid = self-enrolled, ajax.php:64)
  duplicates: keep a completed row (earliest completiondate), else the lowest id; timecreated MIN; others merged

local_plan_course_status -> lp_course_status (only if rows): pathid, courseid, userid, status and percentage raw
  (no code defines them), startdate, completiondate (0 -> NULL), timecreated, timemodified, usercreated, usermodified
```

### Tenant rule

Normalised `open_path` when its root passes `assert_valid`. Empty (the column was added late, NOT NULL with
no default, `LP db/upgrade.php:305,353`): (1) `'/' . costcenter` when that production-only column exists
and is valid; (2) the root shared by every enrolled user; (3) the creator's root; (4) NULL and reported.
NULL or empty paths are cross-tenant only (`ptm:47-53,68-72`; `tenant.php:380-404`).
2026-10-07 decision XC-TENANT-GUESS: steps (2) and (3) guess a tenant, which BizLMS never did (it scoped by `open_path`
only). Nothing changes today (April: 0 of 17 plans have an empty path). The shared-learner root is a weaker guess (that
tenant's admin already sees those learners): it is reported, not escalated; `creator_root > 0` stops the run for Nitin
(section 16).

### Status mapping

- Path: visible 1 -> 1 Active; 0/NULL -> 0 Archived (`SL view.php:64`; `SL exportcsv.php:62`). Status 2 does
  not exist (`SL db/install.xml:16-17`).
- Users: BizLMS status 1 with completiondate -> 2 Completed, timecompleted = completiondate
  (`lib/lib.php:780-786`; `LP classes/local/userdashboard_content.php:47-48`); status 1 without a date -> 2,
  timecompleted NULL, reported; NULL/0 -> 1 In progress when any course of the path has
  `course_completions.timecompleted > 0` for the user (batched per path), else 0 Enrolled
  (`ptm:924-928`). Completion is not recomputed.

### Side effects to avoid

- Never `path_manager::enrol_users` (`ptm:714-796`, manual enrolments at :832) or `assign_courses`
  (back-fill, :579-591), `create`/`update` (:378-450). No enrol instances (`lib/lib.php:1069,1097-1110`).
- No completions (BizLMS observed `course_completed` to auto-enrol the next course, `LP classes/observer.php:32-45`),
  no BizLMS events or notifications, certificates, points or adaptive decisions. Do not flip
  `sentientia.learningpath.adaptive.enabled` (`SL db/feature_flags.php:36-46`).

### Schema additions

- `learningpath`: + shortname CHAR255, objective TEXT, learning_type INT(2), approvalreqd INT(1) NOT NULL
  DEFAULT 0, selfenrol INT(1) NOT NULL DEFAULT 0, sequential INT(1) NOT NULL DEFAULT 0, points INT, categoryid,
  skillid, levelid, certificateid INT NULL, usercreated, usermodified INT NOT NULL DEFAULT 0; widen name and
  open_path to 255.
- `courses`: + usercreated, usermodified, timemodified. `users`: + enrolledby, timemodified (`timestarted` is NOT built,
  2026-10-07 decision LRN-09).
- NEW `local_sentientia_lp_course_status` (conditional): pathid, courseid, userid, status, percentage,
  startdate, completiondate, timecreated, timemodified, usercreated, usermodified; UNIQUE (pathid, courseid, userid).
- `legacy_planid` and `audience_json` are not built (R6, R7). Both trees (`TOP local/sentientia_learningpath`
  exists alongside the ME copy).

### Code fixes

1. `ptm:296-303`: remove the `local_learningplan_user` fallback in `is_enrolled` (R14).
2. `ptm:343-361`: remove the `count_paths` fallback (it counts every tenant's plans) and replace
   `open_path LIKE :p` (:350) with `path_descendant_filter`.
3. `SL index.php:33-34`: count users with status 2, not paths with status 2.
4. `SL classes/external/list_paths.php:90`: label status 0 "Archived", not "Cancelled" (en + hi).
5. `SL exportcsv.php:72-106`: add Status and Completed on; compute % over mandatory courses, or 100 when status 2.
6. `ptm:942` and `SL classes/external/list_path_courses.php:110`: print a dash, not 1970, for timecreated 0.
7. A learner "My learning paths" page behind a default-OFF flag (no learner page exists,
   `SE local/sentientia_manager/classes/approval_manager.php:837-842`). 2026-10-07 decision F-47: with the reader flag
   flip (Nitin's call), add the navigation entry behind the same flag and point the approval message and the whatsapp
   `send_path_milestone()` link at `mypaths.php` when `sentientia.learningpath.learner_paths.enabled` is ON for the recipient.
8. `SL lib.php` (empty, :1-3): `local_sentientia_learningpath_pluginfile` for `summaryfile`, and render it.
9. Protect history: `unenrol_user` hard-deletes completed rows (`ptm:852-868`); `delete()` cascades users and
   courses (:474-490) but not `lp_course_status` or the copied file. Block or soft-delete; extend the cascade.
   2026-10-07 decision LRN-10: a PENDING imported enrolment with no completion or progress, on an active path, may be
   unenrolled by an admin (BizLMS allowed it); the unenrol result lists the course enrolments that REMAIN from that plan
   (the legacymap `enrolments` rows created from this plan's BizLMS instances, with course links) and removes nothing
   automatically.
10. Privacy (`SL classes/privacy/provider.php:26-35`): the new user columns and table.
11. `SE local/sentientia_org/cli/migrate_all.php:116-134`: retire the `local_learningplan` step (it drops the
    missing `status` source column, :273-277, so hidden plans become Active).

### Fixture

`bizlms_fixture` trait; the five LP tables with the production-only columns. Orgs `/1`, `/1/5`, `/77`,
`/177`. Users u1 (`/1/5`), u2 (`/77`), u3 (`/177`), deleted u4, a creator, a `/77` tenant admin. Courses
c1, c2, c3 with a completion for u1 on c1. Plans P10 (visible 1, `/1/5`, approvalreqd, selfenrol, sequence,
learning_type 1, points 50, certificateid 7, a summaryfile), P11 (visible 0, `/77`, timemodified 0), P12
(`''` with costcenter 177), P13 (255-character name), P14 pre-written at its id by a migrate_all-style copy
(adopted). Courses on P10: c1 'and' 0; c2 'or' 1; duplicate c2; NULL course; missing course; on P11: c3
'AND' with NULL sortorder. Users on P10: u1 (self-enrolled, in progress via c1); u2 completed plus a
duplicate; u4; on P11: u2. One `local_plan_course_status` row. Assert ids, statuses, P12 `/177`, the
255-character name, mandatory flags, dense sortorder, skips, user statuses and enrolledby, file copy,
adaptive_mode 0, empty sinks, unchanged core counts (`user_enrolments`, `enrol`, `role_assignments`,
`course_completions`, `tool_certificate_issues`, `local_sentientia_lp_adaptive_log`), second run no-op, a
manual status 2 not downgraded; tenant checks as the `/77` admin; `is_enrolled` no longer reads the legacy table.

### Verification corrections applied

- Citation paths made explicit (`classes/lib/lib.php`, `classes/render/view.php`).
- Protect-history code fix added (code fix 9).
- Pending approvals moved to the request feature (one owner per table); the approver problem is handled there.
- `reset_sequence` after commit.
- Paths, courses and users run in one feature transaction (`atomic`), so no half state is ever committed.
- Both trees named.
- `assert_valid` kept; a costcenterid-0 fallback is reported.

### Open questions

- Non-completed learners: derive In progress from completions (proposed) or import all as Enrolled?
- Enforce approvalreqd, selfenrol and sequential later (flagged), or store only?
- BizLMS start and end dates: Sentientia's enrolment window (proposed) or display-only columns?
- Show completed history on archived paths? (BizLMS hid visible 0 plans, `LP classes/local/userdashboard_content.php:20,47-48`.)
- Tenant fallback order for empty paths: accept?
- `local_plan_course_status` meaning if it has rows; `moduletype` values other than '' or 'course'.
- Enrol instances `enrol='learningplan'` (`lib/lib.php:1052-1063`): gap G6.

### Decisions of 2026-10-07

- **IDN-04 (cover copy):** the `summaryfile` copy goes through the `copies_files` marker (see section 15 and ADR-032).
- **LRN-08 (cover image):** the imported cover shows on the admin path page (`view.php:76-85`) only when
  `sentientia.learningpath.learner_paths.enabled` is ON (otherwise `has_cover` is false), following the program logo
  precedent (`learningplan.cover_on_admin_view = behind_learner_paths_flag`). April: 1 plan has a cover file. Visual evidence
  of `view.php` with the flag OFF and ON.
- **LRN-09 (`startdate`):** BizLMS never writes `local_learningplan_user.startdate`; the column is not copied and the
  preflight blocker stays (fail closed on unexpected data). If Stage B shows values, the lead traces their writer before
  anything else is decided. No schema is built for an empty column.
- **LRN-10 (unenrol of imported rows, also classroom and program):** an admin may unenrol an imported enrolment that has no
  completion, progress or attendance, on an active path, classroom or program. BizLMS allowed this routine action, and the
  signed 'block every imported row' would have stopped it for the 762 April learners still pending on the 17 active plans.
  Every row that carries history, and every row of an archived path, stays blocked; the BizLMS row stays in the legacy
  archive. On a learning path the unenrol does NOT remove the learner's converted course enrolments (BizLMS did): the result
  lists the course enrolments that remain from that plan, so the admin is told. Nothing is removed automatically (folded
  enrolments may give access for other reasons, and removing access is the harder direction to undo). Backlog: 'path unenrol
  removes course access' as a flagged parity feature for native and imported paths. Signed key
  `framework.protect_imported_history_pending_enrolments = unenrol_allowed_without_progress`. Runbook: no admin unenrol before
  `bizlms_production_open`.
- **LRN-11 (stalled-path nudge):** the notifications rule `rule_learning_path_stalled` never targets imported BizLMS enrolments
  or archived paths (`AND lp.status = 1 AND lp.visible = 1` plus the `provenance::not_imported_sql` fragment, guarded by
  `class_exists`); an admin can still nudge those learners by hand (`learningplan.stalled_nudge_scope =
  native_rows_on_active_paths`). BizLMS sent no such nudge, so an automatic message about a years-old BizLMS enrolment would
  be new behaviour triggered by the import (about 760 April learners). Must land before `smart_rules` is ever flipped; nothing
  fires today (`smart_rules` default OFF, rule not seeded).
- **F-59 (Stage B):** `learningplan.dates_as = enrolment_window` was to be checked at the rehearsal. April: 0 of 17 plans have a
  start or end date, so it has no effect there. At Stage B count plans with an end date in the past and self-enrol on; if any
  exist, report that the enrolment window now refuses new enrolments where BizLMS only displayed the dates.
- **F-54 and F-89 (April expectations for the post-Stage-B acceptance list):** `learningplan:orphan_course` = 4 (122 of 126
  course rows import); 53 learning-plan enrolments of users from another root on `/1` paths, made up of 6 (`/177`), 6 (`/77`)
  and 41 (`/80`, all 41 accounts deleted), stay hidden from tenant readers (ADR-031). Root 100 has 113 users, all deleted, with no
  plan enrolment. (CRS-05's 40 cross-tenant learner-course pairs come from the 12 enrolments of live users.)
- **F-51:** `user_step::has_started()` reads `course_completions` through global `$DB` inside `transform()`: read-only and
  identical in dry and apply runs, so it is recorded in ADR-032 as an accepted read-only exception.
- **XC-TENANT-GUESS:** see the tenant rule above and section 16.
- **Open questions above, answered (signed 2026-09-30):** non-completed learners: In progress when a course is done, else
  Enrolled; approvalreqd, selfenrol and sequential: stored only; start and end dates: Sentientia's enrolment window; completed
  history on archived paths: admins only; tenant fallback order: accepted as written; enrol instances `enrol='learningplan'`:
  gap G6, decided (section 21).

---

## 18. evaluation

**Owner:** `local_sentientia_evaluation` (both trees are byte-identical today for install.xml, upgrade.php,
version.php 2026092500, the managers, the provider and the pages; ship both). **Depends:** org,
classroom, program. **Atomic:** yes. Under the threshold the whole feature commits at once. Above it,
batch mode can leave a form without its answers after a crash; the site stays closed until the marker
is set, and `--resume` completes it.

Short names: `EV` = `BZ local/evaluation/`; `SV` = `SE local/sentientia_evaluation/`; `em` =
`SV classes/evaluation_manager.php`; `ee` = `SV classes/evaluation_engine.php`.

No Sentientia code reads a legacy evaluation table, so no fallback goes silent, and nothing shows until
the import runs.

### Sources and targets

| Source | Target | Id | Key |
|---|---|---|---|
| local_evaluations (`EV db/install.xml:7-54`) | local_sentientia_evaluation | **P (changed from the map)** | map; deleted = 1 rows not imported (reason `deleted_form`) |
| local_evaluation_item, evaluation > 0 (`EV db/install.xml:74-95`) | local_sentientia_evaluation_questions | M | map; info, label, captcha, pagebreak -> archived `not_a_question` |
| local_evaluation_item, evaluation = 0 AND template > 0 | folded into local_sentientia_evaluation_template.payload | | map, folded |
| local_evaluation_template (`EV db/install.xml:55-73`) | local_sentientia_evaluation_template | M | map |
| local_evaluation_completed (`EV db/install.xml:96-115`) | local_sentientia_evaluation_responses | M | map |
| local_evaluation_value (`EV db/install.xml:155-172`) | folded into responses.response_data | | map, folded (UNIQUE completed_item, :170, so the fold loses nothing) |
| local_evaluation_users (`EV db/install.xml:136-154`) | local_sentientia_evaluation_assign | M | map; group (evaluationid, userid): the source is not unique (only non-unique indexes, :150-153; writers guard with record_exists, `EV users_assign.php:157`) |
| completions with no users row | synthesized assign rows | | fan-out of the completed row, subkey `assign` |
| local_eval_completedtmp, local_eval_valuetmp (`EV db/install.xml:116-135,173-190`) | none | | declined: drafts deleted on submit (`EV lib.php:1419-1421`); `guestid` is a sesskey (`EV classes/completion.php:437`) and is never copied |
| local_eval_sitecourse_map (`EV db/install.xml:191-204`) | none | | declined: dead table; rows -> blocker |

Why PRESERVE for `local_evaluations`: core `{event}` rows with plugin `local_evaluation` carry the id
(`EV lib.php:440,481`); `local_classroom.trainingfeedbackid` and `local_classroom_trainers.feedback_id`
point at it (`BZ local/classroom/db/install.xml:31,115`); `local_emaillogs.moduleid` for moduletype `feedback`
(`EV users_assign.php:171-192`). The target is empty at cutover.

### Column maps

```
local_evaluations -> evaluation
  id -> id (preserve)
  name -> name (trim, fit 254; source 255, EV :11; target SV db/install.xml:9)
  intro + introformat -> description = content_to_text(...) with @@PLUGINFILE@@ references removed (intro files are not
                         carried, EV lib.php:160; readers print format_string, SV respond.php:153, SV responses.php:164)
  (const) -> kirkpatrick_level 1 (all BizLMS forms are post-training reaction forms, EV evaluation_form.php:57-59;
             BZ local/classroom/lib.php:131-134; allowed levels em:33-38)
  (const) -> trigger_event 'manual', days_after 0  (never course_completion or classroom_end: ee:65-76 enqueue invites
             for ACTIVE rows with a matching trigger)
  (tenant rule) -> costcenterid (a local_sentientia_org id) and open_path (that org's path) (em:174-178,380-384)
  deleted, visible, timeclose -> status 2 ARCHIVED (em:28-30), see status mapping
  anonymous: 1 -> 1, 2 -> 0 (EVALUATION_ANONYMOUS_YES = 1, NO = 2, EV lib.php:34-35); forced 1 when any completed row of the
             form has anonymous_response = 1 (sticky: EVERY completion of such a form imports anonymous, even one BizLMS stamped
             named; 2026-10-07 decision EV-16, `evaluation.sticky_anonymity = whole_form`)
  timeopen, timeclose -> same (0 = no constraint, SV install.xml:23-26); multiple_submit -> same (default 1, EV :16)
  email_notification -> notify_admin_on_response 0 ALWAYS (1 message_sends every site admin per response,
             em:1120-1128,1575-1591)
  timemodified -> timemodified; timecreated = MIN(timemodified, MIN(users.timecreated), MIN(completed.timemodified))
  evaluationmode -> evaluationmode CHAR(2) NOT NULL DEFAULT 'SE' ('SE' self evaluation, 'SP' supervisor evaluation of a
             team member; written from the declared enum; 2026-10-07 decision EV-17)
  type, evaluationtype, plugin, instance, visible, course, department, audience columns, publish_stats,
  autonumbering, completionsubmit, page_after_submit, usermodified -> not copied (R7; the map's legacy_meta is dropped)

local_evaluation_item -> questions
  evaluation -> evaluationid (map)
  typ + presentation -> questiontype + options JSON:
     multichoice 'r' or 'd' ('r>>>>>A|B|C<<<<<1', EV item/multichoice/lib.php:20-22,442-467) -> 'multichoice', options = normalised texts
     multichoice 'c' -> 'multichoice_multi' (em:519-529)
     multichoicerated ('r>>>>>weight####text|...', EV item/multichoicerated/lib.php:23-27,135-149) -> 'multichoice',
                      options = the texts after '####'; weights dropped (open question)
     numeric ('from|to', EV item/numeric/lib.php:45,234) -> 'numeric', options {min, max} as ints; decimal bounds are
                      truncated by Sentientia (em:903-906,927-932): warning truncated:numeric_bound
     textfield, textarea -> 'text'
  name -> questiontext = trim(html_entity_decode(strip_tags(name)))       required -> required; anonymous 0
  position -> sortorder
  dependitem -> depends_on_qid (second pass through the map; not imported -> NULL)
  dependvalue -> depends_on_value, run through the SAME normaliser as the option texts (trim + html_entity_decode +
     strip_tags; BizLMS compares the raw option text, multichoice/lib.php:420-439, and for multichoicerated the text after
     '####', multichoicerated/lib.php:23; Sentientia compares exact trimmed strings, em:1659-1665)
  dependvalue '' with dependitem > 0 -> depends_on_value = '__bizlms_never__' and warning: in BizLMS it matched nothing
     (multichoice/lib.php:433); in Sentientia NULL would mean "show on any answer" (em:1652-1656). The sentinel keeps the
     child hidden, as BizLMS did.
  label, options ('i', 'h' flags), hasvalue -> not copied; timecreated = the form's timecreated

template items -> template.payload.questions[] {questiontype, questiontext, options[], required, anonymous 0, sortorder}
  (the export_template shape, em:590-599); dependencies dropped (the payload has no field for them)

local_evaluation_template -> template
  name -> name (fit 254); description ''; payload = {format 1, exported_at, evaluation {...}, questions [...]} (em:570-601)
  ispublic -> ispublic (0 private, 1 public, EV lib.php:623-631; SV install.xml:138-139)
  open_path -> costcenterid (org id by path; 0 = unscoped when unresolved, SV install.xml:136-137)
  createdby_userid 0; timecreated, timemodified = import time (no source timestamps)

local_evaluation_completed -> responses
  evaluation -> evaluationid (map; deleted form -> skip)
  anonymous (anonymous_response = 1, or unknown and the form is anonymous, or userid 0 guest, EV classes/completion.php:434-438)
     -> userid 0, subject_userid NULL (Sentientia stores anonymous answers with userid 0, em:1089); sticky: a completion
        BizLMS stamped named (anonymous_response 2) on a form that ever held an anonymous answer is imported anonymous too,
        warning anonymity_made_sticky (2026-10-07 decision EV-16)
  named -> userid = evaluatedby if > 0 else completed.userid (evaluatedby = $USER at save, EV lib.php:1371,1377; added with
     default 0 by EV db/upgrade.php:36-43); subject_userid NEW = completed.userid when evaluationmode 'SP' and it differs
  courseid -> courseid (> 0, else NULL; never set on save, classes/completion.php:433-440)
  plugin 'classroom' -> classroomid = instance if that classroom exists (ids kept); plugin 'program' -> programid (map)
  timemodified -> timesubmitted (0 -> the form's timemodified and reported; must be > 0, em:1295-1299)
  random_response -> not copied

local_evaluation_value -> responses.response_data (JSON keyed by the NEW question id, int keys; every imported question
  gets a key, missing -> null, the shape submit_response writes, em:1077-1097):
     multichoice: 1-based index -> option text; 0 or '' -> null (multichoice/lib.php:197-205,405-411)
     multichoice_multi: '1|3' -> ['A','C'] (multichoice/lib.php:184-195)
     numeric: float string -> number; '' -> null (numeric/lib.php:304-313)
     text: html_entity_decode(value, ENT_QUOTES|ENT_HTML5) (BizLMS stored it s()-escaped, EV item/textfield/lib.php:202-204)
  values of non-imported items dropped; a value from another form or outside the option list -> rejected and reported

local_evaluation_users -> assign (group evaluationid, userid: keep MIN(timecreated) and that row's creatorid; others merged)
  userid -> userid (in SP mode the team member evaluated, EV users_assign.php:173-188)
  plugin 'classroom' -> trigger_event 'classroom_end', source_id = instance; else 'manual', 0
  status: 'responded' if a completed row exists for (form, userid) (EV lib.php:2977-2986); else 'expired' (the form is
     imported archived). The source status column is never written (users_assign.php:145-160) and is ignored.
     2026-10-07 decision EV-15: the status follows EVERY legacy completion of the pair, including completions skipped as
     orphan_user or no_timestamp (both needs-owner), so an assignment stays 'responded' on purpose: 'expired' would invent a
     non-response (for example for an evaluated person whose supervisor did fill in the form but has since left);
     responded_at = MAX over those completions, cut to the day on a protected form
  creatorid -> assigned_by_userid; due_at = timeclose if > 0 else NULL
  responded_at = MAX(completed.timemodified); snapped to 00:00 of that day when the form is identity-protected
  timecreated, timemodified
synthesized assign rows (completions with no users row): trigger 'manual', source_id 0, assigned_by NULL,
  status 'responded', timecreated = completed.timemodified — snapped to 00:00 of that day, with timemodified too,
  when the form is identity-protected (the anonymous response's timesubmitted carries the same minute; list_assignments
  returns a.timecreated, em:1235-1251)
```

### Tenant rule

Resolve an org, never a bare root: (1) `rtrim(trim(open_path), '/')`, `'0'` = unset (default, `EV db/install.xml:36`);
if it is a digit path whose root passes `assert_valid`, find the org by path, dropping the last segment until
found; (2) `'/' . costcenterid` when positive (BizLMS stores the root there,
`EV classes/task/evaluation_due.php:59-67`); (3) plugin `classroom`: the classroom's path; (4) unresolved:
costcenterid 0, open_path NULL, archived, reported (cross-tenant only, `em:82-91,282-288`). 2026-10-07 decision EV-TENANT:
the former step (4), the root of `usermodified` (the tenant of whoever last edited the form), is REMOVED, because that
guess would show named survey answers to admins who never saw them (`evaluation.tenant_editor_fallback = not_used`; see
the decisions block). A template resolves `open_path`, then `costcenterid` (2026-10-07 decision F-23). Questions, responses and assignments inherit through evaluationid (`em:258-270,2128-2155`).

### Status mapping

- deleted = 1 -> not imported: `evaluation_delete_instance` soft-deletes and purges the values
  (`EV lib.php:338-346,373-378`); every BizLMS list hides these rows (`EV lib.php:3129`; `EV classes/local/user.php:198`).
- deleted = 0 -> status 2 ARCHIVED for every row by default: ACTIVE would reopen answering at `SV respond.php:33`
  with no assignment check, and anonymous re-submission is not blocked (`em:1011-1016`) whereas BizLMS blocked it
  (`EV lib.php:1960-1971`).
- Assign statuses: responded / expired / assigned (`SV db/install.xml:164-165`). `expire_assignments` touches only
  `assigned` rows with `due_at` (`SV classes/task/expire_assignments.php:40-46`).

### Side effects to avoid

- Never `submit_response()` (admin messages, time and status checks, `responded_at` = now; `em:1036-1055,1120-1128,1204-1224`).
- No rows in `local_sentientia_evaluation_triggers` and no `timesubmitted = 0` shells (`ee:215-230,232,269-297`).
- Imported forms are `manual` and ARCHIVED, so no observer can enqueue invites for them (`SV db/events.php:28-56`).
- No BizLMS events, calendar events, email-log rows (`EV users_assign.php:161-192`); no classroom feedback flags.

### Schema additions

- `responses`: + subject_userid INT NULL, indexed.
- `local_sentientia_evaluation`: + evaluationmode CHAR(2) NOT NULL DEFAULT 'SE' (2026-10-07 decision EV-17; install.xml plus a
  guarded upgrade step, plugin version above 2026093001, `importer::REQUIRES_VERSION` raised to match).
- The map's `legacy_id` (evaluation, template), `legacy_meta`, `legacy_itemid` and `legacy_completedid`
  columns are not built (R6, R7).
- Privacy strings for `subject_userid` in en and hi.

### Code fixes

1. `SV classes/privacy/provider.php:21-60,62-78,80-138,151-218`: declare and handle `subject_userid` (export
   about the subject; NULL it on erasure, keep the supervisor's row).
2. `em:2236-2315` and `SV response_list.php:44-66`: a "Subject" column for non-protected SP forms.
3. `SV responses.php:81-145`: render `multichoice_multi` and `numeric` buckets (computed at `em:1834-1850,1942-1954`).
4. `em:1902-1911`: accumulate floats, not ints (and `em:919-933` if decimal bounds must survive).
5. `SV response_detail.php:64,82,115-116`: numeric qid keys and list-shaped options, before re-enabling the page
   (it needs `local/sentientia_evaluation:view`, which `SV db/access.php:3-12` does not declare). 2026-10-07 decision
   EV-06: both pages are re-gated on `:manage` behind the new default-OFF flag `sentientia.evaluation.response_drilldown`.
6. A learner evaluation history page behind a default-OFF flag (BizLMS showed them on the dashboard,
   `EV classes/local/userdashboard_content.php:54`).
7. Protect history: `delete()` cascades questions and responses and orphans assign and trigger rows (`em:498-512`);
   `update_question` can retype answered questions (`em:800-874`). Refuse on imported forms (owner decision);
   make delete cascade assign and trigger rows.
8. Only if still-open forms are activated: `has_user_responded` must also check assign `responded` (`em:1011-1016`).
9. `list_templates(0)` (`em:1486-1498`) is unscoped: any future picker applies ADR-031 scope.
10. Found in passing: the invite URL passes `evaluationid` (`ee:275-277`) but `SV respond.php:14` requires `id`.

### Fixture

`bizlms_fixture` trait; the nine EV tables from a checked-in copy (with `evaluationmode`, `evaluatedby`,
`deleted`, `open_path`). Orgs `/1`, `/1/5`, `/77`, `/177`. Users in `/1/5` and `/77`, a supervisor, two tenant
admins, a suspended and a deleted learner. Forms: (a) anonymous, two anonymous completions by one user plus a
guest; (b) named, one completion, one pending user, one completion with no users row, and a duplicate users
pair; (c) SP named, a supervisor completion and an old one with evaluatedby 0; (d) classroom trainer feedback;
(e) deleted; (f) path '0', costcenterid '77'; (g) named now, one old anonymous completion; (h) `/1/5/999`;
(i) unresolvable. Items: multichoice r, c, d; multichoicerated; numeric `'1.5|9.5'` with answer `7.25`; a
textfield with `'Tom &amp; Jerry &#039;x&#039;'`; textarea; info; label; pagebreak; captcha; a dependent item
with dependvalue `'B'` on an option `'<b>B</b>'` (matches after normalising) and one with dependvalue `''`
(sentinel). A template with two items; tmp rows with a guestid. Assert: 8 forms imported with their original
ids ((e) skipped, (i) cost centre 0); anonymous rows userid 0 and day-snapped responded_at and synthesized
timecreated; SP subject_userid; response_data keys and values; dependency remap and sentinel; tenant scope as
each admin; all ARCHIVED and manual; no triggers or shells; empty sinks; tmp tables untouched; second run no-op.

### Verification corrections applied

- Both trees.
- `dependvalue` normalised like the options; `''` becomes a sentinel that keeps the child hidden.
- `local_evaluation_users` de-duplicated before insert (a UNIQUE violation would roll back the whole form).
- Synthesized assign rows snapped to the day on protected forms.
- Citation paths: `classes/completion.php`, `classes/local/userdashboard_content.php`, `classes/task/evaluation_due.php`.
- Privacy strings in en and hi.
- Numeric bound truncation reported, not implied lossless.
- Framework: `local_evaluations` ids are kept (the map planned new ids).

### Open questions

- Still-open forms: ARCHIVED (proposed) or ACTIVE after cutover?
- multichoicerated: `multichoice` (proposed) or `rating` when the weights are exactly 1..5?
- SP forms set to anonymous: hide the subject too (proposed)?
- After a verified import, anonymise the legacy `userid`/`evaluatedby` of anonymous rows, or drop the legacy
  tables ([CONFIRM], Nitin)? They still link anonymous answers to people (`EV classes/completion.php:435-440`).
  Answered: neither here; see 2026-10-07 decision EV-19 below.
- Trainer feedback forms (one per trainer, same name, `BZ local/classroom/lib.php:199-202`): add the trainer's name?
- Make imported forms read-only?

### Decisions of 2026-10-07

- **EV-16 (sticky anonymity; blocks Stage B):** a form that EVER collected an anonymous answer (its flag, or any completion
  with `anonymous_response` 1) imports EVERY completion anonymous (`userid` 0, no subject), including completions BizLMS stamped
  named (2) (`evaluation.sticky_anonymity = whole_form`). Pooling means no anonymous answer can be singled out by subtracting
  the named ones, and it matches `identity_protected()`, which already hides every respondent of such a form, so keeping the
  named rows named would gain nothing anyone can see (it would only keep a DB-level name on those rows, and the legacy table
  holds that name anyway). Warning `anonymity_made_sticky`. April: no form is affected (`anonymous` is
  2 on all 3 forms and `anonymous_response` is 2 on the 1 completion). The importer declares the key (`importer.php`
  `DECISIONS`), so a decisions file without it blocks the feature; the test helper `decisions_accepting()` carries it.
- **EV-TENANT:** a BizLMS form whose path, stored root (`costcenterid`) and parent classroom give no tenant imports PATHLESS
  (costcenterid 0, cross-tenant callers only, archived, reported). BizLMS's readers scoped forms by `open_path`
  (`BZ local/evaluation/analysis.php:57`, `classes/responses_table.php:346`), so such a form was visible to site admins only;
  an editor's root is a guess at ownership, the reason the signed `classroom.pathless = cross_tenant_only` rejects
  `by_creator`. Steps 2 and 3 stay: `costcenterid` is the root BizLMS itself stored for the form and used for its own
  notifications. April: no form resolves through the old step 4 (form 1 is deleted; form 2 has an unregistered `/101` root and
  no stored root, so it is already pathless; form 3 has `/1/116`).
- **EV-17 (evaluation mode; before the learner page can go on; preferably before the Stage B rehearsal so it runs the final
  schema):** `evaluationmode` is built so Sentientia knows a form was a supervisor evaluation and the person being evaluated is
  never told they 'responded' (anonymous SP forms, old SP completions with `evaluatedby` 0). `form_step` writes it from the
  declared enum; `learner_history::for_user` filters `evaluationmode = 'SE'` for assignments and responses and drops
  `is_supervisor_evaluation()`; `shows_subject()` requires `'SP'`; `response_step` warns `sp_responder_unknown` for an SP
  completion with `evaluatedby` 0 (it still follows the column map); a new `verify()` check `imported_form_mode_mismatch`. If
  it lands later, an idempotent backfill from `local_evaluations.evaluationmode` through the map is acceptable. April has
  `evaluationmode` {SE} only, so no real row changes today. The privacy provider is unchanged (no person column).
- **EV-19 (legacy anonymous linkage; blocks Stage B as a decisions-file edit):** the link from anonymous answers to people stays
  in the legacy tables, untouched, until the separate legacy-table privacy ADR (`framework.legacy_table_privacy`). Its scope is
  WIDER than the legacy tables: it must also cover the import's own map (the 'assign' sub-row and the anonymous response share
  one completion id) and the completion-ordered ids of implied assignments, with a test that no `legacymap` row joins an
  anonymous response to an assign row. That ADR is a precondition for anonymising or dropping the legacy evaluation tables, NOT
  for cutover. The Stage B report records the count of implied assignments on identity-protected forms (ids only). April: 0
  anonymous completions and 0 completions without an assignee row.
- **EV-06 (response pages):** `response_list.php` and `response_detail.php` came back dead (they required
  `local/sentientia_evaluation:view`, which no `access.php` declares, so nobody could open them, site admins included). They are
  gated on `:manage` behind the new default-OFF flag `sentientia.evaluation.response_drilldown` (a 'not available' notice when
  OFF); roles: manager archetype only (role 1, role 9 tenant administrator, site admin); trainers (role 10, teacher archetype)
  and employees get nothing, which matches BizLMS (role 9 held viewreports and viewanalysepage; role 10 was prohibited from
  both). `require_evaluation_access()` and `identity_protected()` stay. `responses.php` shows an 'Individual responses' link only
  when the flag is ON. Recommended future flip (Nitin's call): ON for Airpay after he has reviewed the screenshots. The two
  `BASELINE` entries in `capability_names_test.php` go in the same change (F-31); no version bump (the flag registry is cached
  and purged on deploy).
- **EV-18 and EV-20-NOTE (learner page `my_evaluations.php`):** the anonymous note is reworded (en and hi) so it does not claim
  more anonymity than is true for imported forms ('your answers are not linked to you' is false at DB level while the legacy
  tables and the import map still link them; a notice to a data principal must be accurate). Revisit only if the privacy ADR
  later makes the stronger claim true. Screenshots (named responded, anonymous with the new note, waiting, closed, the imported
  badge, and the SP case after EV-17; desktop and 590 px; test persona and test forms only, no real name) wait for Nitin's
  answer on how the flag is turned on for the evidence (questions list in `OWNER-DECISIONS-2026-10-07.md`). The production flip
  is already decided: `framework.reader_flags_airpay_at_cutover`.
- **EV-23:** no evaluation needs-owner reason is pre-accepted (`value_not_valid`, `duplicate_value`, `foreign_item`,
  `missing_item`, `orphan_*`, `no_timestamp`, `unmapped_enum`); April produces none, and acceptance only means something after
  the counts are seen.
- **EV-03-KP:** numeric questions with a 1..5 range do NOT count as ratings in the Kirkpatrick roll-up on `analysis.php`
  (BizLMS had no roll-up; a 1..5 number is not necessarily a satisfaction scale; the import never invents meaning). April's
  only real form has five such questions. A future 'numeric scale counts as rating' option would be a flagged product feature.
- **EV-11-PUB and EV-35:** another tenant's 'public' templates are NOT listed to a tenant admin (nothing crosses a tenant); the
  unused help string `template_ispublic_help` is reworded (en and hi) when a picker is built, and any sharing later must follow
  the cross-tenant route. The old roadmap items (cohort-scoped triggers, a per-customer template library, an e-mail reminder for
  unfinished surveys) move to the product backlog as unscheduled entries, each with a spec and a default-OFF flag: April shows
  BizLMS never used survey reminders (no `feedback_due` type, 0 configured feedback notification rows, 0 feedback e-mail log
  rows, 0 forms with an open and close window).
- **EV-36:** the bulk-assign web service refuses an empty audience (`bulk_assign_pick_at_least_one`), as the form does; an
  explicit whole-tenant assignment (`org_path` = the tenant root) still works and reports capped. No flag, no version bump.
- **EV-FIX-FLAGS:** the admin-page fixes on `claude/eval-followups` (Subject column EV-02, numeric and tick-all statistics EV-03,
  `response_detail` keys EV-05, read-only controls EV-09) ship UNFLAGGED: they are corrections, or they only affect imported
  data. Merge only after PHPUnit and the screenshots listed in
  `docs/visual-evidence/2026-10-07/eval-followups/README.md`; the `response_list` and `response_detail` shots wait for the EV-06
  flag.
- **EV-25 and EV-26 (framework, see ADR-032):** merge order (the classroom merge b316c138a and the dry-run fold fix 6991ac1b6
  are committed; program is merged) and the id-sequence floor.
- **F-23 (map corrections, EV-22):** reason codes: needs-owner `duplicate_value`, `foreign_item`, `missing_item`; others
  `item_not_imported`, `response_not_imported`, `orphan_item`, `orphan_template`. Warnings: `responder_not_found`,
  `anonymity_made_sticky`, `deferred:local_classroom`, `deferred:local_program`, and `sp_responder_unknown` after EV-17.
- **F-29 and F-30:** `form_facts::pair()` and `answers()` read per completion through a 64-entry cache (an N+1 at scale; April:
  1 completion and 5 values): time the evaluation feature on the Stage B copy and batch the pair facts per page of completions
  if it matters for the window. Process rule: before merging any importer, run its preflight read-only against `bizlms_april`
  and cross-check every `source_spec` enum against a real value histogram (counts only); fixture tests could not catch
  `anonymous_response = 2` because the checked-in install.xml documents the column as 0/1.
- **Open questions above, answered (signed 2026-09-30):** still-open forms: archived; multichoicerated: plain multichoice;
  SP forms set to anonymous: hide the subject too; trainer feedback forms: keep the BizLMS name; imported forms read-only: yes.

---

## 19. request

**Owner:** `local_sentientia_request` (both trees; `TOP local/sentientia_request/version.php:44` is also
2026092500). **Depends:** classroom, program, learningplan. **Atomic:** no.

Short names: `RQ` = `BZ local/request/`; `api` = `RQ classes/api/requestapi.php`; `SQ` =
`SE local/sentientia_request/`; `rm` = `SQ classes/request_manager.php`.

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_request_records (`RQ db/install.xml:39`, columns :41-58) | local_sentientia_request (`SQ db/install.xml:7-57`) | map; duplicates are legal in the source (`api:93-95`) and stay separate rows |
| local_learningplan_approval (`BZ local/learningplan/db/install.xml:124-144`) | local_sentientia_request, item_type `path` | map; folded (`dup_of_request`), not merged, when an imported records row exists for the same (userid, path) (2026-10-07 decision F-81) |
| local_request_comments (`RQ db/install.xml:8-23`) | folded into decision_note | map, folded; expected empty; preflight BLOCKS if it has rows (2026-10-07 decision COMMS-R4) |
| block_request_records, _comments, _config | none | if a table exists with rows: blocker `needsowner` (written by `RQ admin/comment.php:228`, `deny_course.php:310`, `bulk_deny.php:151`) |
| local_request_config (`RQ db/install.xml:26-35`) | none | declined: form settings; its only reader, `RQ classes/form/request_form.php:58-118`, is never instantiated |
| local_request_formfields, local_request_form_data, local_crequest_* | none | existence check; rows -> blocker |

### Column maps

```
local_request_records -> request
  createdbyid -> userid (RQ :42; api:84)
  compname -> item_type: elearning -> 'course', learningplan -> 'path', classroom -> 'classroom', program -> 'program',
     certification -> 'certification' (api:70-77,316-339). ANY OTHER VALUE -> blocker: the WS takes it as PARAM_RAW
     (RQ classes/external.php:137,159) and create() stores anything (api:76-80)
  componentid -> itemid: course id as is; path/classroom/program through their feature maps; certification: the legacy id,
     unmapped (gap G3). A path, classroom or program that no longer exists -> itemid 0 (renders '(deleted item)'), because
     those features keep their ids and a later item could receive the same id (2026-10-07 decision COMMS-R2); course ids are
     core ids that are never reused, so they keep the source id
  componentid -> courseid when 'elearning', else 0 (as submit_path writes, rm:199)
  (requester) -> costcenterid = tenant::root_for_user(the full restored user row), as submit() does (rm:71-74); 0 if unresolvable
  (none) -> reason '' (NOT NULL text, SQ install.xml:21); readers show a lang string for imported rows
  status -> status: see mapping
  responder -> decided_by_userid (0/NULL -> NULL) (api:176,262)
  approver_userid: decided rows -> the responder; pending course rows -> rm::route_approver($user, $courseid)[1]
     (rm:289-308); pending path rows -> route_approver_for_path (rm:246-255); pending classroom/program/certification
     rows -> NULL (kept out of every inbox, SQ classes/external/list_pending.php:46). The FULL user row is passed:
     resolve_supervisor reads open_supervisorid / open_managerid from it (rm:269-281); a partial row routes every
     pending request to default_approver (rm:305-307). `rm::route_approver` became `approver_routing` (2026-10-07 decision F-81).
     2026-10-07 decision COMMS-R1: a pending course or path row whose requester is deleted or suspended, or whose item no
     longer exists, is NOT routed: it imports as ['pending', route admin, approver NULL] with warning `pending_history_only`
     (`request.pending_stale = history_only`), like classroom and program rows
  respondeddate -> timedecided (decided rows only) (api:177,263)
  timecreated -> COALESCE(timecreated, timemodified, respondeddate, 0) (RQ :54 nullable)
  timemodified -> COALESCE(timemodified, respondeddate, timecreated, 0)
  route: decided -> 'admin'; pending -> the routing label; pending non-course/path -> 'admin'
  timedue NULL, timeescalated NULL (escalation selects timedue < now, rm:498-500)
  legacy_source 'bizlms' NEW in-row marker
  module_id (= componentid, api:81-82), usermodified, compcode, compkey, req_type, req_values, c1, c2, c3 -> not copied;
     preflight counts non-NULL values of the last seven and stops for the owner if any exist

local_learningplan_approval -> request (item_type 'path', itemid = the path id, courseid 0; the submit_path shape rm:195-199)
  userid; approvestatus -> status (0 pending, 1 approved, 2 rejected; BZ local/learningplan/classes/lib/lib.php:476-485,511-523)
  approvedby -> decided_by_userid and approver_userid when approvestatus != 0; usermodified when approvedby is 0
  pending rows -> approver_userid via route_approver_for_path (as above)
  reject_msg -> decision_note (usually NULL: lib/lib.php:520 writes an undefined variable)
  timecreated; timemodified, and timedecided when decided; reason ''; route per `request.decided_route` and the routing
  label, NOT 'manager' (2026-10-07 decision F-81); timedue NULL
  costcenterid = the requester's root; orphan planid -> skip

local_request_comments -> decision_note of the imported row whose source id = CAST(instanceid AS INT):
  '[Y-m-d H:i] <fullname of createdbyid>: ' + html_to_text(message), joined by newline, oldest first (PARAM_TEXT reader,
  SQ classes/external/list_mine.php:130); dt is read as a string (invalid XMLDB type 'datetime', RQ install.xml:13);
  rebuilt identically on every run; orphans reported
  2026-10-07 decision COMMS-R4: preflight blocks (`needs_owner:request_comments_present=N`) when the table has rows, and
  `request_manager::decide()` on an imported row appends to the folded note and never replaces it
```

### Status mapping

`PENDING` -> `pending` (`api:88`); `APPROVED` -> `approved` (`api:180`); `REJECTED` -> `rejected` (`api:266`).
Target values: pending|approved|rejected|cancelled|expired (`SQ db/install.xml:24`). Nothing in local_request
writes `COMPLETE` (only dead code into another table, `RQ lib/requestlib.php:192-193`); any other value
blocks. Caveats: a classroom APPROVED row can mean waitlisted (`api:213-228`); a PENDING row can be stale
after a failed approve that still notified (`api:186-206`); a REJECTED row can hide an earlier approval
plus unenrolment (`api:273-277`); deletes are hard (`api:155`). For learning-plan approvals, status 2 also
covers "removed from plan" (`BZ local/learningplan/classes/lib/lib.php:511-522`).

### Tenant rule

The source has no tenant column; BizLMS scoped requests by the requester's `open_path`
(`RQ classes/export/requestview.php:117-118,149-151`). `costcenterid` = the requester's current root; 0 is
visible to cross-tenant callers only (`tenant.php:335-349`; `SQ tests/tenant_scope_test.php:93-113`).
Roots outside the registry are reported.

### Side effects to avoid

Never `submit`, `submit_path`, `decide` or `cancel` (`rm:103-118,209-230,446-460`); no messages (`SQ classes/notifier.php:67-82`);
no enrolments (`rm:418-419,632-650`); no escalation (timedue NULL); no BizLMS behaviour replayed
(`api:113-116,152-153,197-200,273-277,286-289`; `RQ classes/notification.php:124-128`).

### Schema additions

`local_sentientia_request`: + legacy_source CHAR(40) NULL. The map's `legacy_id`, `legacy_itemid` and
UNIQUE `idx_legacy` are not built (R6). If comments exist and Nitin wants them attributable: a
`local_sentientia_request_comment` table instead of folding.

### Code fixes

1. Item names: `list_mine.php:79-87`, `list_pending.php:77-91`, `SQ classes/external/list_all.php:80-94` join only
   `{course}`, so every non-course request reads "(deleted course)" (`list_mine.php:188`). LEFT JOIN the path,
   classroom and program tables; a placeholder for certification; extend search.
2. `SQ index.php:25`, `approvals.php:26`, `all.php:27`: "Item" label via get_string, en + hi.
3. `rm::decide` refuses item types other than course and path (`error_invalidstate`); otherwise an approval marks
   the row approved while the enrolment fails silently (`rm:413-426`). No Approve/Reject buttons for them
   (`list_pending.php:120-133`).
4. `rm:525-527` (auto_expire) and `rm:498-500` (escalate) add `AND legacy_source IS NULL`, so the first cron
   run does not flip imported pending history to `expired` (`rm:520-536`).
5. `list_mine::shape` (`list_mine.php:194`): a lang string for an empty reason on imported rows.
6. `list_all::execute_returns` (`list_all.php:113-127`) declares `status_badge` and `status_badge_class`, which
   `all.php:28` renders.
7. `approvals.php:28` expects `due_badge`, which list_pending never returns (`list_pending.php:139-163`).
8. `list_pending` default sort `timedue ASC` (`list_pending.php:23,41-43`) puts imported NULL-due rows first on
   MySQL: order NULLs last.
9. Privacy export writes only `course_id` (`SQ classes/privacy/provider.php:68`): add item_type and itemid.
10. Optional: show `route` through the lang strings (`SQ lang/en:46-48`) instead of the raw value.

### Fixture

`use \local_sentientia_org\test\bizlms_fixture;` and `$this->ensure_bizlms_schema()` (a trait,
`bizlms_fixture.php:33,39`). Legacy tables built per `RQ db/install.xml` (dt as char(20)) and the learning-plan
approval table. Users: `/1/5` with a live supervisor, `/77`, empty path, a responder, deleted and suspended
requesters. Items: two courses, a path, a classroom, a program (with their maps), a certification id. Rows:
pending elearning; approved elearning; rejected learningplan; pending classroom; approved program; a duplicate
pair; NULL timecreated; missing course; a 'COMPLETE' row (blocks); an unknown compname (blocks); a learning-plan
approval duplicating an imported records row (merged) and one pending (routed). Comments for one request and
an orphan. Assert empty sinks, unchanged enrolment and roster counts, the mapping, routing, NULL timedue,
second run identical, reader checks, auto_expire and escalate return 0 for imported rows, decide refuses the
classroom row.

### Verification corrections applied

- Unknown `compname` blocks.
- Entry points include `BZ local/courses/classes/output/search.php:401`; every write still goes through requestapi.
- Both trees.
- Fixture trait usage corrected.
- The path duplicate guard (`rm:171-178`) is a risk like the course one (`rm:66-67`).
- NULLs-last ordering in the approvals inbox.
- Privacy export carries the item.
- The full user row is passed to routing.
- `local_learningplan_approval` moved here from the learningplan map (one owner).

### Open questions

- Legacy PENDING requests: actionable in the inbox (default above for course and path), read-only, or closed as expired?
- Pending classroom and program requests: extend `decide`, or keep them as history?
- Certification requests: keep unmapped (proposed) or map to programs?
- Route label for decided legacy rows: 'admin' (proposed) or a new 'legacy'?
- Show rows BizLMS hid (deleted components, deleted or suspended requesters, `RQ classes/export/requestview.php:124-139,153`)?
- Accept the requester's current path as the tenant?
- Legacy pending approvers: Sentientia routing (proposed) or a named L&D admin?

### Decisions of 2026-10-07

- **COMMS-R1 (stale pending requests; blocks Stage B):** see the approver rule above (`request.pending_stale = history_only`).
  Approving such a row would enrol and message an account that has left, or point at nothing; admins still see it in All
  requests. `verify()` asserts no imported pending row has an approver whose item is gone. The flag
  `sentientia.request.imported_history` is turned ON only after this has landed (COMMS-C2). April: every request table is
  empty, but 1,452 of 2,187 non-deleted tenant-1 users are suspended, so stale pending requests will mostly belong to people
  who have left.
- **COMMS-R2 (blocks Stage B):** a request whose path, classroom or program no longer exists gets `itemid` 0. Those features
  keep their ids (PRESERVE) and reset their sequence, so a deleted item that had the highest id would otherwise hand its id to
  the next native item: the old request would show the new item's name, trip the `submit_path` duplicate guard, and a
  cross-tenant admin's approval would enrol the learner in an unrelated path. ADR-032 rule 6 (the sequence floor, EV-26) stays
  as the primary defence and this as defence in depth.
- **COMMS-R3:** the reader changes that fix native screens ('Item' header, route in words, status badges in All requests, the SLA
  column, real names on path requests) ship UNFLAGGED as bug fixes: every path request read '(deleted course)', `all.php`
  rendered badges `list_all` never returned and `approvals.php` expected a `due_badge` nobody supplied (code fixes 1 to 10).
  Sentientia is not live, so BizLMS production is untouched. The flag still keeps imported rows out; its description is
  reworded from 'look exactly as they did before the import' to 'leave the imported rows out' (flag registry and README).
  Visual evidence (desktop and mobile) of My requests, Pending approvals and All requests.
- **COMMS-R4 (blocks Stage B):** BizLMS has no writer for `local_request_comments` (its comments went to
  `block_request_comments`, `BZ local/request/admin/comment.php:228`), so the table is expected to be empty and nobody knows who
  could see such rows; `list_mine` would show the folded note to the requester, which could widen visibility. Preflight blocks
  when it has rows, and `decide()` appends to the folded note instead of overwriting imported evidence. April: 0 comment rows.
- **COMMS-R5:** `approvals_step` folds a DECIDED learning-plan approval into a PENDING records row for the same user and plan
  and drops the approval's newer state; accepted, because BizLMS has no insert into `local_learningplan_approval` (only
  `update_record`, `BZ local/learningplan/classes/lib/lib.php:484,522`) and April holds 0 rows (preflight reports the count).
  Stage B trigger: if `learningplan_approvals` > 0, a decided approval is NOT folded into a pending records row and imports as
  its own path row.
- **COMMS-R6:** routing can pick a supervisor who lacks `local/sentientia_request:approve`, and imported rows carry no `timedue`
  (`verify()` forbids a deadline on them), so they never escalate. Not changed: native and imported routing stay identical
  (`approver_routing` is shared on purpose). A runbook line only, and optionally a Stage B report count of imported pending rows
  whose approver lacks the capability. April: 0 request rows.
- **COMMS-C1:** see section 11 (the four request and notifications reasons are not pre-accepted).
- **F-77 (before cutover):** `verify()` treats only pending, approved, rejected and expired as valid; add `cancelled`, or a
  requester cancelling an imported pending row after go-live makes a later verify fail falsely. **F-80 (before cutover):**
  delete the dead `request_manager::get_course_owner_userid()` (a duplicate of `approver_routing`); add
  `legacy_source IS NULL` to the deletes in `cli/smoke_request.php` and `cli/seed_qa_pending_request.php`
  (`framework.protect_imported_history`); wrap `drop_field` in `try/finally` in the upgrade test; test dt '0000-00-00 00:00:00'.
  **F-78:** until classroom and program landed the registry raised `unknown_dependency` for request and evaluation; the merges
  close it (ADR-032 Stage B gate 7); then add one real cross-importer test and drop the stand-ins. **F-79 (after Stage B):**
  framework boilerplate (a `contract_dependencies()` hook and a re-seed after `contract_clear_import`, several fixture XMLs,
  bulk user attributes in `lookups`, documentation of the read-only `approver_routing` pattern and of fold versus merge across
  steps).
- **F-81 (map corrections):** approvals are `folded` (`dup_of_request`), not `merged`; the approval route follows
  `request.decided_route` and the routing label, not 'manager'; `rm::route_approver` became `approver_routing`; the unmapped C
  and D tables are declared, with a preflight blocker; the plugin ships TWO fixture XMLs, so the one-fixture-per-plugin wording
  in ADR-032 and in `tools/check-bizlms-fixture-copies.php` is amended.
- **F-82:** `accepted_reasons` naming: see section 23.
- **Open questions above, answered (signed 2026-09-30 and above):** legacy PENDING requests: course and path stay actionable (a
  person still decides), classroom, program and certification are read-only, stale ones are history only (COMMS-R1);
  certification: unmapped; decided route label: `admin`; rows BizLMS hid: shown; tenant: the requester's current root; approvers:
  Sentientia routing.

---

## 20. ratings

**Owner:** `local_sentientia_ratings` (both trees byte-identical today). **Depends:** classroom, program,
learningplan. **Atomic:** no.

Short names: `RA` = `BZ local/ratings/`; `SR2` = `SE local/sentientia_ratings/`; `rtm` = `SR2 classes/rating_manager.php`.
The dead `SE local/airpay_ratings` plugin receives nothing; installing it next to sentientia_ratings is a
fatal collision (`SE docs/adr/ADR-022-component-rename.md:196-202`).

### Sources and targets

| Source | Target | Key |
|---|---|---|
| local_rating (`RA db/install.xml:4-24`) | local_sentientia_ratings (`SR2 db/install.xml:5-24`) | map; group (userid, mapped itemid, mapped area) against the target UNIQUE (:22) |
| local_comment (`RA db/install.xml:26-43`) | NEW local_sentientia_ratings_reviews | map; **no** natural unique key (2013-era rows are distinct reviews, `RA comment.php:29-31`) |
| local_like (`RA db/install.xml:44-65`) | NEW local_sentientia_ratings_reactions | map; group (userid, itemid, ratearea) against a UNIQUE on the new table (2026-10-07 decision F-35: the column is `ratearea` on all three target tables) |
| local_ratings_likes (`RA db/install.xml:66-84`) | none | declined: a derived cache rewritten on every write (`RA update.php:42-62`; `RA index.php:55-72`); used as the parity oracle |

### Column maps

```
AREA MAP (all three tables): local_courses -> local_sentientia_courses (the only area the live reader asks for,
  TOP theme/sentientia/classes/output/core_renderer.php:922,1707); local_classroom -> local_sentientia_classroom;
  local_program -> local_sentientia_programs; local_learningplan -> local_sentientia_learningpath
  (whitelisted, SR2 classes/external/submit_rating.php:39-42). local_certification and any other area -> skip and report
  (reason unknown_area) until a target exists. Any area longer than 100 characters -> skip (source CHAR255, RA :10,:30,:50;
  target CHAR100, SR2 :9).
ITEM: local_courses itemid = course.id (skip when the course does not exist); classroom/program/learningplan through their
  maps; NULL -> skip.

local_rating -> ratings
  itemid, ratearea (maps above); userid (NULL, <= 1 or missing -> skip; rtm:184)
  rating: integers 1..5 only; NULL, 0, < 0, > 5 -> skip (the WS takes any PARAM_INT, RA classes/external.php:334,364)
  timecreated = COALESCE(timecreated, timemodified, 0); timemodified = COALESCE(timemodified, timecreated, 0)
  moduleid -> not copied (no live writer; preflight counts non-NULL and checks it equals itemid)
  duplicates (no unique index, RA :20-23): winner MAX(COALESCE(timemodified, timecreated)), then MAX(id); others merged

local_comment -> reviews
  itemid, commentarea -> itemid, ratearea (maps); userid (skip NULL, <= 1, missing)
  comment -> review verbatim (raw PARAM_RAW input, RA classes/external.php:89; the reader must escape it)
  timecreated = COALESCE(timecreated, timemodified, 0), or the 2013 `time` column when present; timemodified likewise
  2013-era courseid, activityid (RA comment.php:22-28) -> not copied

local_like -> reactions
  itemid, likearea -> itemid, ratearea; userid (skip as above); likestatus kept (NULL -> 0); timecreated, timemodified
  1 = like, 2 = dislike (RA index.php:33; RA lib.php:43,59); other values stored but never counted
  duplicates (RA :60-63): as for ratings
```

### Tenant rule

No tenant column on either side. Averages and like counts are site-wide per item, as in BizLMS
(`RA lib.php:258-259`; `rtm:36-41`). A review **list** shows names and pictures, so its reader limits the
listed reviewers to the viewer's tenant (`tenant::scope_path` + `path_descendant_filter` on `u.open_path`,
`tenant.php:132-140,444-466`). The importer reports cross-tenant raters and keeps them.

### Parity oracle

Compare imported averages and counts with `local_ratings_likes.module_rating` (what non-enrolled viewers and
course tiles saw, `RA lib.php:102-103`; `BZ local/courses/classes/external.php:1083,1241`), within 0.05, and
explain each difference (dedupe, skipped rows, mobile likes never updating the cache, `RA classes/external.php:264-292`;
BizLMS 2 dp over all rows vs Sentientia 1 dp over rating > 0, `RA lib.php:258-266`; `rtm:37-43`).
`block_trending_modules` holds a third copy (fed by `RA update.php:63-71`) and is not written.

### Side effects to avoid

Never `rating_manager::submit_rating` or the submit WS (they stamp `time()`, `rtm:195,214-215`, and throw on
userid <= 1, `rtm:184`). No enrolments to justify old rows. No events, messages, points, webhooks. Do not
write `local_ratings_likes` or `block_trending_modules`.

### Schema additions

- NEW `local_sentientia_ratings_reviews`: id, itemid NOT NULL, ratearea CHAR100 NOT NULL, userid NOT NULL, review TEXT NULL,
  timecreated, timemodified; FK userid; indexes (itemid, ratearea), non-unique (userid, itemid, ratearea).
- NEW `local_sentientia_ratings_reactions`: id, itemid, ratearea, userid, likestatus INT(1) NOT NULL DEFAULT 0,
  timecreated, timemodified; FK userid; UNIQUE (userid, itemid, ratearea); index (itemid, ratearea, likestatus).
  (2026-10-07 decision F-35: the area column is named `ratearea` on all three tables; this document said `area`.)
- `legacyid` columns are not built (R6).
- Privacy (`SR2 classes/privacy/provider.php:51-67`): metadata, export and delete for both tables; en + hi.
- Flags `sentientia.ratings.reviews` and `sentientia.ratings.reactions` (default OFF) for the new readers.

### Code fixes

1. `rtm:42-46,49-61,89-94`: remove the legacy fallback (R14). As written it compares the new area name with rows
   stored under the old name, so it never matches, and its per-item switch hides history for any item that gets one
   new row.
2. `TOP theme/sentientia/classes/output/core_renderer.php:923-930,1708-1715`: delete the dead BizLMS fallback branch.
3. `SR2 classes/external/submit_rating.php:45-49`: drop the BizLMS-era areas from the whitelist after the import, or
   one item's ratings split into two averages.
4. Initialise the rating widget on course pages (no `js_call_amd('local_sentientia_ratings/rating_widget', 'init')`
   exists in theme/sentientia), or render read-only. 2026-10-07 decision CRS-11: both, behind the new default-OFF flag
   `sentientia.ratings.widget` (see the decisions block).
5. New flag-gated readers: a review list (escape with `s()`/`format_text(FORMAT_PLAIN)`; BizLMS printed it raw,
   `RA lib.php:303-307`; tenant-scoped reviewers; blank reviews hidden) and like/dislike counts (status 1 and 2 only).
6. `SR2 README.md:3-5,17,26-28,34-35`: correct the claims (no tenant gate, no review column; DSR delete deletes rows,
   `SR2 classes/privacy/provider.php:145,158,177`, so aggregates drift from the cache after an erasure).
7. Count these tables in `migration_parity_check.php` (the migration plan notes it does not, :165-168).
8. Remove the dead `SE local/airpay_ratings` directory ([CONFIRM] delete; ADR-022:187-194). 2026-10-07 decision CRS-13:
   NOT done; it waits for Nitin's [CONFIRM].

### Fixture

`bizlms_fixture` trait. Legacy tables per `RA db/install.xml` (all nullable, non-unique indexes), plus a
`local_comment` variant with the 2013 `time` column. Users `/1/5`, `/77`, `/177`, deleted. Courses c1 `/1`, c2 `/77`.
Ratings: u1 c1 4 (source timestamps); u2 c1 5 and an older duplicate 3; u3 c1 NULL; u3 c2 7; userid 0; a missing
course; a classroom rating with id map {10 -> 10}; a certification area; an unknown area; a 150-character area;
non-NULL moduleid. Comments: `'<script>x</script>good'` with NULL timemodified; `''`; two distinct reviews by u1 on
c1 (both imported). Likes: 1, 2, NULL and a duplicate. A stale cache row. A native Sentientia rating (u1 c2) inserted
first. Assert area and item mapping, source timestamps, dedupe winner, skips with reasons, `get_average(c1)` = 4.5
over 2, `get_user_rating(c1, u1)` = 4, both u1 reviews present and stored verbatim, native row unchanged, second
run no-op, dry run writes nothing, legacy tables unchanged, empty sinks, cache unchanged, privacy export and erase.

### Verification corrections applied

- Area length guard (> 100 skipped).
- Unknown and certification areas are skipped and reported (the map contradicted itself; keep-verbatim would
  write rows no reader asks for).
- Reviews: no unique natural key; 2013-era multi-row groups are distinct reviews and are all imported.
- `save_comment` takes the author from a caller-supplied parameter, not `$USER` (`RA classes/external.php:88,101,118`):
  `local_comment.userid` is asserted, not authenticated. `RA delete.php:1-12` hard-deletes any comment with no
  login or sesskey, so review history may be incomplete. Both go in the parity report.
- BizLMS's review list joined the rating through the LIKE row (`RA classes/lib/ratinglib.php:60-61`), so it showed
  'N/A' when the reviewer had not liked; the new reader does not copy that bug.
- The UNIQUE-on-nullable rationale is moot (no legacyid column).
- README fix includes :26-28.
- Readers to replace also include `BZ local/courses/classes/local/general_lib.php:325`.
- Course existence is checked in the column map, not only the fixture.

### Open questions

- I-20 counts per table and area; SHOW CREATE TABLE for the four tables.
- Which entity replaces `local_certification`, and its area name?
- Skip invalid rows (proposed) or quarantine them?
- Blank reviews: import and hide (proposed) or skip?
- Production `local_ratings/review_enable` (`RA settings.php:28-33`, default 0): if 0, learners never saw reviews.
- Reaction and review flags ON for Airpay at cutover (BizLMS showed likes on course tiles)?
- Show dislike counts?
- Keep deleted users' ratings (proposed)?

### Decisions of 2026-10-07

- **CRS-10 (skipped rows; accepted after Stage B with counts):** the skips stand. `invalid_rating`, `invalid_reaction`,
  `orphan_user`, `orphan_item` and `unknown_area` each need the owner's written acceptance, after Stage B and only for a
  non-zero rehearsed count (a re-approval event); every skipped row stays in the legacy tables, which are the archive. Expected
  on April: `unknown_area` about 195 (194 `local_like` rows plus 1 `local_rating` row with a NULL area; the 194 like rows hold
  web-vulnerability-scanner probe strings in `likearea`, 19 of them longer than 100 characters, timestamps 2024-01 to 2025-12,
  none with a real user); `orphan_item` about 85 or more (84 ratings on courses that no longer exist, plus ratings on the 1 of 4
  rated learning plans that is gone); `invalid_rating` 1; `orphan_user` at most 1; `invalid_reaction` 0 (`likestatus` is only 1
  or 2). The Stage B parity report labels the scanner rows (`ratings:unknown_area`).
- **F-41 (Stage B report; security backlog):** the 194 scanner rows are evidence that BizLMS's like endpoint stored
  unauthenticated or unvalidated writes. Sentientia does not reproduce it: reactions are read-only and `submit_rating` is
  login-required with an area whitelist (`services.php:16-22`). Add the fact to the production security audit backlog; under
  the replace-not-patch rule nothing is patched on live BizLMS.
- **CRS-11 (rating widget):** the theme calls `render()` with the default interactive = true and never initialises the widget
  (`theme/sentientia/classes/output/core_renderer.php:927-928` and `1705-1706`), so learners see controls that do nothing. A new
  default-OFF flag `sentientia.ratings.widget`: OFF renders `rating_manager::render($itemid, $area, false)`, read-only stars; ON
  renders interactive stars plus `js_call_amd('local_sentientia_ratings/rating_widget', 'init')`. Recommended future flip (not
  decided): ON for Airpay at cutover after Nitin reviews the visual evidence (desktop and mobile, flag OFF and ON), because
  BizLMS let learners rate courses.
- **CRS-12 (reader flags at cutover; recorded recommendation, the flip stays Nitin's call):** `sentientia.ratings.reviews` stays
  OFF for Airpay at cutover (April: `local_ratings/review_enable` = 0 and `local_comment` has 0 rows, so BizLMS learners never
  saw reviews, and a review list would be more visible than BizLMS, owner rule 3; re-check `review_enable` on the Stage B
  copy). `sentientia.ratings.reactions` goes ON only after the reaction counts are wired into a theme template (BizLMS showed
  like and dislike counts on learning plans: 33 likes and 2 dislikes on April; Sentientia's reaction counts are not yet wired
  into any template, so flipping it now would show nothing) and the visual evidence is reviewed.
- **CRS-13:** the dead `moodle-enhancement/local/airpay_ratings` directory (14 tracked files, never packaged, restorable from
  git; it declares the same global function as `sentientia_ratings`, so installing both is fatal) is a delete and WAITS for
  Nitin's [CONFIRM]. NOT executed.
- **F-35 correction:** the area column on all three target tables is `ratearea`.
- **Open questions above, answered (signed 2026-09-30):** invalid rows: skip; blank reviews: import hidden; deleted users'
  ratings: keep; dislike counts: shown behind the reaction flag; certification area: skipped and reported until a target
  exists. The row counts per area are read at Stage B (F-42).

---

## 21. Gaps: data with no map yet

Each gap keeps parity at exit 2 (unclaimed table with rows, or a needs-owner reason) until a map exists or
Nitin accepts it. All must be closed or accepted before cutover.

| # | Gap | Evidence | Proposed next step |
|---|---|---|---|
| G1 | Certificates: `tool_certificate_issues` rows with `moduletype` classroom, program, learningplan and the `local_certificate` registry | production `moduletype` is NOT NULL with no default (`BZ admin/tool/certificate/db/install.xml:35-36`); issued at `BZ local/classroom/classes/local/general_lib.php:72`, `BZ local/learningplan/classes/render/view.php:2080,2656`, `BZ local/program/classes/output/renderer.php:793`; `TOP admin/tool/certificate/db/install.xml` has no `moduletype` column, so stock inserts on the restored table may fail under MySQL strict mode (inference, untested); no Sentientia reader found | a certificates map (owner TBD); a Stage B test of one native issue on the restored table |
| G2 | `local_challenge` (peer challenges: who challenged whom on which module) | indexes added unguarded (`BZ local/learningplan/db/upgrade.php:184,272-288`), so the table existed; rendered at `BZ local/learningplan/classes/render/view.php:1537-1539` and `TOP theme/airpayux/classes/output/core_renderer.php:1106-1109`; `SE local/sentientia_challenge` has no reader | preflight SHOW COLUMNS; a challenge map into sentientia_challenge |
| G3 | `local_certification` and request rows with compname `certification` | `BZ local/request/classes/api/requestapi.php:327`; `BZ local/request/classes/export/requestview.php:142`; plugin not in the snapshot | keep request rows unmapped (section 19) until an owner entity exists |
| G4 | `local_positions`, `local_domains` | unguarded indexes (`BZ local/users/db/upgrade.php:67-68,107-117`); read at `BZ local/users/classes/local/user.php:117-149`; `user.open_positionid/open_domainid` point at them (`BZ local/users/db/upgrade.php:23-27`); Sentientia shows the bare ids (`SE local/sentientia_users/classes/user_fields.php:61-62`) | users feature: two lookup tables with preserved ids, plus the profile label fix |
| G5 | Tag instances of the classroom, learning plan and evaluation tag areas | `BZ local/classroom/db/tag.php:28-35`; `BZ local/learningplan/db/tag.php:28-35`; `BZ local/evaluation/db/tag.php:30-31`; uninstall deletes instances (`BZ tag/classes/area.php:386-400`) | counted in preflight; Sentientia tag areas needed before any uninstall |
| G6 | Orphaned BizLMS enrol instances (`enrol` = classroom, program, learningplan with `customint1`) and their `user_enrolments` | `BZ local/classroom/classes/classroom.php:1781-1786,1829-1846`; `BZ local/program/classes/program.php:1489-1531`; `BZ local/learningplan/classes/lib/lib.php:1052-1063`; Sentientia ships only `enrol/sentientiasub` | DECIDED 2026-09-30: convert each enrolment to a manual enrolment (signed `gap.orphan_enrol_instances = convert_to_manual`) and built (importer `enrolments`); the 2026-10-07 rules (CRS-01, CRS-02, CRS-03, CRS-05, XC-G6-WHY) are below the table |
| G7 | Core `{event}` rows with BizLMS columns (`plugin`, `plugin_instance`, `local_eventtype`) | columns added by `BZ local/costcenter/db/install.php:31-66`; rows by classroom (`BZ local/classroom/classes/classroom.php:183-205,342-362`), program (`BZ local/program/classes/program.php:170-228`), evaluation (`BZ local/evaluation/lib.php:440,481`); `SE local/sentientia_calendar` has no reader | Stage B check of how the 5.2 calendar renders them; hide or map |
| G8 | Skill and level tags on classrooms and programs | `BZ local/classroom/db/install.xml:67-68`; `BZ local/program/db/install.xml:45-46` | they stay in the legacy tables; decide whether Sentientia needs them |
| G9 | `course.open_path` may be NOT NULL without a default on the restored database | upgrade path `BZ local/courses/db/upgrade.php:171-177` vs install path `BZ local/courses/db/install.php:29-31` | Stage B check; if so, core course creation and restore fail in strict mode |
| G10 | BizLMS notification types with no Sentientia sender | April types in use: `course_complete` 5,783 (including 1,921 manager copies), `course_enrol` 5,316, `learningplan_enrol` 2,264 and `users_welcome_email` 839 (37 types and 15 templates are defined). Sentientia has a learner completion rule (seeded in `db/upgrade.php`, `observer.php`) and a welcome e-mail in `local_sentientia_users`; templates for `course_enrolled` and `learning_path_enrolled` exist (`email_context.php:130,142`) but nothing sends them (`db/events.php` observes only `course_completed`), and nothing sends a manager a completion copy | 2026-10-07 decision COMMS-N7 (`gaps.notification_sender_parity = build_flagged_off`): build the three senders in `local_sentientia_emails` (course enrolment on core `user_enrolment_created`, learning-path enrolment on the learningpath enrol event, manager copy of course completion from the same observer with the manager audience), each behind its own default-OFF flag in `db/feature_flags.php` and each writing `delivery_log` rows with a `template_key`. Turning them ON for Airpay at cutover is Nitin's call after UAT evidence; losing three of the four e-mail types Airpay users get today would break current behaviour |

### G6 rules (2026-10-07 decisions CRS-01, CRS-02, CRS-03, CRS-04, CRS-05 and XC-G6-WHY)

- **Why the conversion is needed (XC-G6-WHY corrects the signed reason):** core grants course access through any ENABLED enrol
  instance whether or not its plugin is on disk (`require_login` -> `enrol_get_enrolment_end`, `moodlelib.php:2575`, filters only
  `e.status` and `ue.status`, `enrollib.php:1281-1285`). The real problem the conversion solves is that orphan instances cannot
  be managed (no unenrol, suspend or expiry handling) while they keep granting access. The signed 'why' said they 'stop
  granting course access'; it is corrected in the decisions file.
- **CRS-01 (blocks Stage B):** after a row is converted, a Sentientia unenrol or suspend of the manual row does not revoke access
  while the BizLMS instance is still enabled. So each fully converted BizLMS instance is DISABLED (`enrol.status` 1, never deleted,
  the prior status kept in the trail table `local_sentientia_courses_enroloff`, recompute step
  `enrolments.legacy_instances_off`, key `enrolments.bizlms_instances_after_verify = disable_when_converted`), and only when, for
  EVERY (user, course) pair that holds a row on the instance, core's access window after the step (manual enrolments only)
  covers the window before it (manual plus BizLMS): a window covers another when it does not start later and does not end
  earlier, 0 meaning 'never ends'. One regression blocks the step for that instance and is reported (ids only), and an instance
  with an unsettled needs-owner row stays enabled. `verify()` and the step share one set-based comparison per pair (the SQL
  equivalent of `enrol_get_enrolment_end` with and without the BizLMS instances); a one-learner spot check is too weak for a
  step that changes the access path of every converted pair. The Stage B report prints the pair count and the regressions.
  Undo is one UPDATE (`UPDATE {enrol} SET status = priorstatus` from the trail). NEVER use 'Delete' on a disabled BizLMS
  instance in a course's Enrolment methods page: delete removes its `user_enrolments` rows. April: 136 learningplan instances,
  all enabled, holding 16,830 active rows; every `role_assignment` has component '', so roles are untouched either way.
- **CRS-02:** an active enrolment row on a DISABLED BizLMS instance converts as SUSPENDED
  (`enrolments.disabled_instance_row_status = convert_as_suspended`): BizLMS grants nothing on a disabled instance, the import
  must never give access BizLMS did not give, and the suspended manual row is how an admin restores it. April: 0 such rows
  (all 136 instances are enabled).
- **CRS-03:** a course whose only manual instance is DISABLED gets a NEW enabled manual instance beside it, recorded in the
  legacymap (target table `enrol`, outcome `imported`) (`enrolments.disabled_only_manual_instance = add_enabled_beside`); the
  admin's disabled instance is untouched. April: 0 such courses (every one of the 71 affected courses has exactly one enabled
  manual instance).
- **CRS-04 (accepted after Stage B):** the three exception rules `enrolments:user_deleted`,
  `enrolments:manual_enrolment_inactive` and `enrolments:manual_enrolment_ends_sooner` stay skips; each is accepted only after
  Stage B with its rehearsed count and a per-learner list (ids only) that L&D has reviewed, and only if the count is above 0
  (April 0, 0, 0). Under CRS-01 the BizLMS instance holding any such row stays enabled, so those learners keep today's access
  until L&D acts. (The descriptive pseudo-key `accepted_reasons` in the decision text is NOT a decisions-file key.)
- **CRS-05:** the 40 learner-course pairs on April that cross tenants (24 learners rooted `/177` and 16 rooted `/77`, all in
  `/1` courses, enrolled through learning plans) convert like every other row. They exist in production today; the conversion
  changes the enrol method, not the tenant of the user or the course; ADR-031 lets the course's tenant admin remove them, and
  the roster readers for scoped callers filter other tenants' learners. The preflight warning
  `legacy_enrolments_across_tenants` with its root histogram, plus a list of course ids and pair counts (no person data), goes
  to L&D at Stage B.
- **F-34 and F-36 (runbook):** see `MIGRATION-REHEARSAL-RUNBOOK.md`, "BizLMS import: Stage B checks". The rehearsal parity
  compare must explain the `user_enrolments` and `enrol` deltas (about 7,733 added `user_enrolments` rows on April, and
  `enrol.status` changes) through the legacymap and the `enrolmove` ledger; see ADR-032 parity hook 4.
- **F-39 (before UAT sign-off and cutover):** readers that count enrolments count each converted learner twice (April: 4,832
  pairs are already doubled and the import adds 7,733 more): `analytics_manager.php:61` and `:68`, `catalog_manager.php:344`,
  `commerce.php:181` and `:243`, `ai_recommender.php:233`. Use `COUNT(DISTINCT ue.userid)` with `ue.status = 0 AND e.status = 0`;
  `get_in_progress` (`catalog_manager.php:414-426`) joins every enrolment, so it returns duplicate course rows and also shows
  suspended enrolments: group by course and filter for active enrolments; add a test with one learner holding a manual and a
  BizLMS enrolment. The report's `sentientia_pages` homepage.php and onboarding.php paths no longer exist under `classes/` and
  need re-locating.

---

## 22. Sentientia runtime code that reads or writes legacy tables

Every entry must be fixed in the release named, so no reader depends on a legacy table after cutover
(the intended in-place readers of `local_costcenter` and the notification templates excepted).

| Code | Legacy access | Fix (owner feature) |
|---|---|---|
| `SE local/sentientia_pages/qr_scan.php:43-46,56-62` (both trees) | WRITES `local_classroom_attendance` | Phase 0 / classroom code fix 1 |
| `SE local/sentientia_pages/qr_attendance.php:54-58` | reads legacy sessions and classroom | Phase 0 / classroom code fix 2 |
| `SE local/sentientia_classroom/classes/session_manager.php:57-66,89-103,110-134` | whole-table and per-id fallbacks | classroom code fix 3 |
| `SE blocks/sentientia_trainer/block_sentientia_trainer.php:39-51` | falls back to `local_classroom` | classroom code fix 10 |
| `TOP theme/airpayux/layout/dashboard.php:175,177` | unscoped count of `local_classroom` | classroom code fix 11 |
| `SE local/sentientia_learningpath/classes/path_manager.php:296-303,343-361` | `is_enrolled` and `count_paths` fallbacks | learningplan code fixes 1-2 |
| `SE local/sentientia_ratings/classes/rating_manager.php:42-46,49-61,89-94` | per-item fallback | ratings code fix 1 |
| `TOP theme/sentientia/classes/output/core_renderer.php:923-930,1708-1715` | BizLMS `display_rating()` branch | ratings code fix 2 |
| `SE local/sentientia_catalog/classes/category_manager.php:23` | reads `local_custom_category` | course_lookups code fix 1 |
| `SE local/sentientia_exams/classes/exam_manager.php:107-118` | `local_onlinetests` fallback | exams code fix 2 |
| `TOP theme/airpayux/classes/output/core_renderer.php:1726-1730,1745-1748` | unguarded SQL on `local_onlinetests` | exams code fix 4 |
| `SE local/sentientia_emails/classes/legacy_bridge.php:117-136` via `manage_controller.php:39-44` | counts `local_emaillogs` site-wide | notifications code fix 5 |
| `SE local/sentientia_emails/classes/legacy_bridge.php:36-53,97-104` | notification templates, read in place | intended; tenant filter fix (notifications code fix 6) |
| `SE local/sentientia_org/classes/accesslib.php:410-414,538-540,609-611`; `org_manager.php:98-99,142-143,172-174,197-198,546-568`; `SE local/sentientia_core/classes/org_legacy_source.php:75,96-97` | `local_costcenter` in place | intended; org code fixes 3-4 |
| `SE local/sentientia_pages/cli/setup_costcenters.php:57-86`, `setup_bizlms_data.php:57-100`, `fix_all_bizlms_data.php:116` | WRITE `local_costcenter`, `local_userdata`, `local_course_types` | Phase 0: refuse on a database holding legacy tables |
| `SE local/sentientia_org/cli/migrate_all.php`, `SE local/sentientia_org/data_migration.php` | copy scripts | Phase 0: refuse, point to `import_bizlms.php` |

The top-level copies of every plugin need the same fix (R5); only `sentientia_pages` was compared so far.

---

## 23. Decisions file keys

`docs/cutover/bizlms-import-decisions.json` holds one value per key. A required key with no value blocks
its feature. The proposed value is the default the rehearsal uses unless Nitin changes it.

| Key | Values | Proposed |
|---|---|---|
| `tenant.unresolved.<feature>` | `pathless` \| `skip` | `pathless` (visible cross-tenant only), always reported |
| `accepted_reasons` (a top-level list of `"<feature>:<code>"` strings) | one string per needs-owner reason | none now: the owner adds each reason that actually occurs, with its Stage B count, after the rehearsal (2026-10-07 decisions IDN-02, EV-23, COMMS-C1, CRS-04, CRS-07, CRS-10, `cart.accepted_reasons`, F-82). The old name `accept_needsowner.<feature>.<reason>` never existed in the loader |
| `classroom.status_new_hold` | `add_5_6` \| `collapse_active` | `add_5_6` |
| `classroom.waitlist_closed` | `removed` \| `waiting` | `removed` |
| `classroom.pathless` | `cross_tenant_only` \| `by_costcenter` \| `by_creator` | `cross_tenant_only` |
| `program.completed_without_date` | `completed_flagged` \| `not_completed` | `completed_flagged` |
| `program.inactive` | `archived` \| `draft` | `archived` |
| `program.empty_levels` | `skip` \| `import` | `skip` |
| `program.deleted_users` | `import` \| `skip` | `import` |
| `program.pathless` | `creator_root` \| `cross_tenant_only` | `creator_root` |
| `learningplan.not_completed` | `derive_in_progress` \| `enrolled` | `derive_in_progress` |
| `evaluation.open_forms` | `archived` \| `active` | `archived` |
| `evaluation.multichoicerated` | `multichoice` \| `rating_when_1_5` | `multichoice` |
| `evaluation.sp_anonymous_subject` | `hidden` \| `shown` | `hidden` |
| `cart.synthesize_ledger` | true \| false | `false`, signed 2026-09-30 (no invented money rows; not a Finance question) |
| `cart.order_tenant` | `buyer` \| `course` | `buyer` |
| `cart.abandoned` | `admin_only` \| `skip` | `admin_only` |
| `cart.imported_visibility` | `admin_only` | `admin_only` (signed 2026-09-30) |
| `cart.admin_refund_imported_orders` | true \| false | `false` (signed 2026-09-30) |
| `cart.credit_balances` | `frozen_pending_finance` | `frozen_pending_finance`: accepted 2026-10-07 under delegation, Finance NOT consulted |
| `cart.erpnext_invoices_legal` | `reference_only_pending_finance` | `reference_only_pending_finance`: accepted 2026-10-07 under delegation, Finance NOT consulted |
| `cart.cash_drawer_rows_without_order` | `import_admin_only` | `import_admin_only` (signed 2026-09-30) |
| `cart.stale_task_adhoc_rows` | `do_not_delete_here` | `do_not_delete_here` (signed 2026-09-30; April: 0 rows) |
| `recompletion.rule_tenant` | `global` \| `per_tenant` | `global` |
| `recompletion.preview_attempts` | `import` \| `skip` | `skip` |
| `request.pending` | `actionable` \| `readonly` \| `expired` | `actionable` for course and path; classroom, program and certification always read-only |
| `request.certification` | `unmapped` \| `programs` | `unmapped` |
| `request.decided_route` | `admin` \| `legacy` | `admin` |
| `request.hidden_rows` | `show` \| `filter` | `show` |
| `skills.catalogue_scope` | `shared` \| `tenant` | `shared` |
| `skills.merge_categories` | `exact_name` \| `none` | `exact_name` |
| `skills.level_proficiency` | CSV level id -> 1..5 | filled 2026-10-07 (LRN-07): 17 April levels by the name rule, level 16 reviewed to 2 |
| `skills.source_label` | `import` \| `course` | `import` |
| `skills.history_from_archive` | true \| false | open |
| `skills.skillmatrix` | `decline` \| `import` | `decline` |
| `users.transcript_status_map` | the list in section 10 | required if the table has rows |
| `users.admin_runs_tenant` | `zero` \| `uploader_root` | `zero` |
| `users.uniquelogins` | `import` \| `skip` | open (depends on logstore retention) |
| `notifications.import_bodies` | true \| false | true, with redaction |
| `notifications.deleted_recipient_sent` | `sent_with_note` \| `suppressed` | `sent_with_note`, for a recipient already deleted when BizLMS ran the send (wording corrected 2026-10-07, COMMS-N6) |
| `ratings.invalid_rows` | `skip` \| `quarantine` | `skip` |
| `ratings.blank_reviews` | `import_hidden` \| `skip` | `import_hidden` |
| `ratings.deleted_users` | `keep` \| `skip` | `keep` |
| `exams.multi_quiz` | `per_quiz` \| `final_only` | `per_quiz` |
| `course_lookups.featured_scope` | `rehome` \| `global` | `rehome` |

The keys added or corrected on 2026-10-07 under Nitin's delegation (status `accepted`, why starts
`[delegated 2026-10-07]`):

| Key | Recorded value | Decision |
|---|---|---|
| `org_roles.user_without_tenant` | `skip_fail_closed` | IDN-01 |
| `framework.file_rehome_copies` | `reviewed_copy_only_marker_all_callers_left_on_purge` | IDN-04 |
| `org.crosstenant_platform_role` | `created_by_adr031_script_no_members_until_named` | IDN-05 |
| `users.logindays_erasure` | `delete` | IDN-06 |
| `users.sync_history_visibility` | `runs_tenant_wide_lines_uploader_only` | IDN-07 |
| `framework.imported_rows_on_admin_pages` | `history_rows_behind_reader_flag_entities_unflagged` | XC-IMPORTED-HISTORY-READERS |
| `framework.protect_imported_history_pending_enrolments` | `unenrol_allowed_without_progress` | LRN-10 |
| `evaluation.sticky_anonymity` | `whole_form` | EV-16 |
| `evaluation.legacy_anonymous_linkage` | `untouched_pending_legacy_privacy_adr` (why widened) | EV-19 |
| `evaluation.tenant_editor_fallback` | `not_used` | EV-TENANT |
| `enrolments.bizlms_instances_after_verify` | `disable_when_converted` | CRS-01 |
| `enrolments.disabled_instance_row_status` | `convert_as_suspended` | CRS-02 |
| `enrolments.disabled_only_manual_instance` | `add_enabled_beside` | CRS-03 |
| `gap.orphan_enrol_instances` | `convert_to_manual` (why corrected) | XC-G6-WHY |
| `course_lookups.coursedetails_candidate_columns` | `leave` | CRS-06, CRS-15 |
| `recompletion.inferred_reset_without_evidence` | `latest_source_evidence_plus_1s` | LRN-01 |
| `recompletion.legacy_rows_on_history_page` | `behind_evidence_view_flag` | LRN-02 |
| `recompletion.dpdp_archive_free_text` | `cleared_record_kept` | LRN-03 |
| `recompletion.imported_rule_enable` | `blocked_until_engine_parity` | LRN-04 |
| `learningplan.cover_on_admin_view` | `behind_learner_paths_flag` | LRN-08 |
| `learningplan.user_startdate` | `block_if_present` | LRN-09 |
| `learningplan.stalled_nudge_scope` | `native_rows_on_active_paths` | LRN-11 |
| `program.nameless_with_shortname` | `import_under_shortname_with_warning` | LRN-12 |
| `program.delete_imported_level` | `blocked` | LRN-13 |
| `classroom.cotrainer_sessions` | `every_session_of_their_classroom` | LRN-15 |
| `classroom.trainer_erasure` | `core_release_dpdp_keep` | LRN-16 |
| `classroom.new_states_ui` | `unflagged` | LRN-17 |
| `notifications.team_member_copy_body` | `withhold` | COMMS-N2 |
| `notifications.course_link` | `moduleid_for_course_templates` | COMMS-N3 |
| `gaps.notification_sender_parity` | `build_flagged_off` | COMMS-N7 |
| `request.pending_stale` | `history_only` | COMMS-R1 |
| `request.comments` | `fold_into_decision_note` (why extended) | COMMS-R4 |

The file holds policy only, no personal data. Its sha256 is stored on every run and pinned from the
Stage B rehearsal to cutover (`--expect-decisions-hash`). After 2026-10-07 it holds 138 decisions, a top-level
`delegated_on` and `delegation_note` beside `approved_by` and `approved_on`, and no `accepted_reasons` list.

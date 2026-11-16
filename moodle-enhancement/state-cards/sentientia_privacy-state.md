# State Card — `local_sentientia_privacy`

**Component:** `local_sentientia_privacy` (was `local_airpay_privacy` until the ADR-022/025 rename)
**Version:** `2026111300` / `1.1.0`
**Maturity:** `MATURITY_STABLE`
**Status:** Part of Sentientia LMS (not live yet: the local copy and UAT hold a test import of a production
backup). DPDP request hub, plus the supplementary erasure provider for stock `tool_dataprivacy` (1.1.0).
**Last refreshed:** 2026-11-17 (erasure completeness 1.1.0: read-only proof, review fixes, re-tested)

---

## Mission

Privacy request hub — receives data-export / data-deletion requests
from users and orchestrates the response across every other plugin's
privacy provider. Pairs with Moodle's built-in `tool_dataprivacy`
flow but adds an Airpay-specific consent-log table for marketing /
analytics opt-ins.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_privacy_requests` | Per-user data-export / data-deletion request rows |
| `local_privacy_consent_log` | Append-only consent ledger (marketing opt-in, analytics opt-in, etc.) |

## Capabilities (2)

`local/sentientia_privacy:` `view` (archetype user), `manage` (no archetype; the admin panel also needs
cross-tenant, ADR-031).

## Feature flags (2, since 1.1.0; both default OFF, read at global scope 0/0 only)

| Key | What it turns on |
|-----|------------------|
| `sentientia.privacy.erasure_completeness.enabled` | Truncation recovery: contexts core's data-request approval drops (contextlist key holes) are erased/exported through their owning component |
| `sentientia.privacy.completion_gap.enabled` | Completion gap: `course_completions` rows core_course never discovers (no criteria / untouched criteria) are erased/exported through core_course |

Turning either ON is Nitin's call and needs a recorded DPO retention decision first (it erases data that survives
today only because of the core defects).

## Key files

```
local/sentientia_privacy/
├── version.php                                  2026111300 / 1.1.0
├── README.md
├── index.php                                     DPDP request dashboard (self-service + admin panel)
├── styles.css
├── classes/
│   ├── privacy_manager.php                       DPDP request orchestrator (process_deletion Step 0)
│   ├── privacy/provider.php                      Own metadata/export; supplementary erasure hooks (1.1.0)
│   ├── local/erasure_completeness.php            Supplementary erasure logic (plan, apply_delete, apply_export)
│   ├── local/erasure_plan.php                    Plan value object (ids and component names only)
│   ├── local/erasure_plan_store.php              Per-process plan store (\core\di, reset per PHPUnit test)
│   ├── local/forced_contextlist.php              Contextlist with a forced key hole (self-check + tests)
│   └── event/erasure_supplemented.php            Audit event, one per supplementary delete
├── db/
│   ├── install.xml                              2 tables
│   ├── access.php, messages.php, upgrade.php
│   └── feature_flags.php                        2 flags (1.1.0)
├── templates/
├── tests/                                       privacy_manager, erasure_scope, admin_scope, erasure_completeness
└── lang/
    ├── en/local_sentientia_privacy.php
    └── hi/local_sentientia_privacy.php          (kn/mr/sw partial)
```

## Tests

`tests/privacy_manager_test.php`, `tests/erasure_scope_test.php` (DPDP flow), `tests/admin_scope_test.php`
(@group tenant_isolation), `tests/erasure_completeness_test.php` (@group privacy_erasure, 1.1.0). Last local run
2026-11-17 (Moodle 5.1.3+, XAMPP), after the review fixes: all four green (erasure_completeness_test 16 tests);
see the 2026-11-17 entry below.

## Open items

- [x] PHPUnit for `privacy_manager::process_deletion()` - tests/privacy_manager_test.php (2026-09-24)
- [ ] Per-customer consent banner customisation
- [ ] Auto-expire stale data-export request files
- [ ] Behat coverage of the request dashboard
- [ ] Integration audit — verify every `local_airpay_*` and
      `local_sentientia_*` plugin has a privacy/provider.php class
- [ ] Reference card: how to extend Moodle's privacy provider
      pattern with Airpay-specific consent records

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Erasure asks every Sentientia provider; admin panel fixed (1.0.2, 2026092400)

**Defect.** `process_deletion()` erased a hand-kept list of eight tables and marked the request
`completed`. Every other table a Sentientia plugin keeps about a person survived: the WhatsApp send
log (each row holds the mobile number), cart credits, calendar feed tokens, the agent audit and more.
It is the 2026-09-22 defect again (a success the code did not achieve), just outside the list that
fix touched.

**Fix.** A new Step 0 calls `delete_data_for_user()` on every `local_sentientia_*` privacy provider
(`erasure_providers()`, not this plugin's own), passing exactly the contexts the provider reports.
Most use the system context; the calendar uses the user context. Core providers are deliberately
not called: this flow keeps anonymised learning records for audit. A provider that throws is added
to the same `$missing` list as an absent table, so the request reads `partial` and the note names
it. The Step 2 `$missing = []` reset, which would have wiped that evidence, is gone.

**Admin panel (index.php)**
- Approve called `process_deletion($reqid)` without the required `$adminid`, so every click died
  with an ArgumentCountError. It now passes `$USER->id`.
- Approve and Reject act only on a `pending` request. A replayed link can no longer re-run an
  erasure or overturn a decision.
- After Approve the page reads the stored status back. A partial erasure gets an error
  notification instead of "processed successfully".
- Each status is counted explicitly. "Rejected" was total minus pending minus completed, so every
  partial request was shown as rejected. There is a new "Incomplete" tile.
- Partial rows show an Incomplete badge, the note, and "Needs manual follow-up".
- A guard comment at the gate: the request list is not tenant-scoped, so `:manage` must not be
  granted to tenant admins until it is.

**Tests.** `tests/privacy_manager_test.php` seeds rows in five tables owned by four plugins and
asserts on the rows. It also checks that a substituted failing provider
(`tests/fixtures/failing_erasure_provider.php`) gives `partial` with the provider named in the note,
while everything else is still erased. Strings added in en and hi.

**Same day, after the 38-provider audit.** Step 0 calls a provider's `anonymise_data_for_user(approved_contextlist)`
when it has one, and `delete_data_for_user()` otherwise. The method is duck-typed (`method_exists`), so no plugin
depends on this one. Learning-path, classroom, compliance-report, xapi and proctoring implement it. They keep
completions, attendance, exemptions, cmi5 attempts and proctoring verdicts keyed to the anonymised user row, as this
flow has always promised, and still erase the personal data around them. Before this, Step 0 would have deleted those
records, a regression from the old eight-table behaviour. The audit also found scope bugs in providers that Step 0 now
reaches (xapi, evaluation, WhatsApp, manager, courses, aiquiz), each fixed in its own plugin.
`tests/erasure_scope_test.php` seeds another person's rows next to the subject's and asserts that they survive.

## 2026-09-24 - Erasure review follow-up

Review follow-ups. Approve on a request that is not an account deletion, or whose user row is gone, now says nothing changed (`erasurenotapplicable`, en + hi) instead of reporting an erasure. Only a stored status of 'partial' gives the 'some data could not be erased' message. The Step 0 comment no longer claims the coverage test sees tables created at runtime or only in upgrade.php. Test fixes: the partial-erasure test expects its `debugging()` call. `erasure_scope_test` adds a proctoring reviewer case and a courses decider case.

## 2026-09-25 - ADR-031: the admin panel needs a cross-tenant caller (1.0.3, 2026092500)

The DPDP admin panel in `index.php` lists every tenant's requests (names, emails, reasons) with no tenant
filter, and Approve erases the requester's data for any request id. It opened for site admins OR any holder of
`local/sentientia_privacy:manage`. `:manage` has no archetype default, so nobody else reached it, but a per-tenant
DPO grant would have let one tenant's DPO erase another tenant's people (ADR-031 decision 3). The gate is now
`privacy_manager::can_administer()`: a site admin, or a `:manage` holder who is also cross-tenant
(`local/sentientia_platform:crosstenant`). No behaviour change today: no role holds `:manage`. `version.php` now
declares the `local_sentientia_platform` dependency. Tests: `tests/admin_scope_test.php` (@group tenant_isolation),
written, not run.

Open (product decision, not built): a per-tenant DPO panel. It needs the request list scoped with
`tenant::path_descendant_filter()` on `u.open_path` (fail closed on a null `scope_path()`) and
`tenant::require_same_tenant_user()` before Approve and Reject. The index.php header still lists it as planned.

## 2026-11-13 - Erasure completeness, "Option A" (1.1.0, 2026111300)

**Why.** The A4 rehearsal (`docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md`) showed stock
`tool_dataprivacy` erasure leaving about 17,219 rows of the 286 erased subjects behind, from two core defects
(Moodle 5.1.3+, the same files in 5.3):
1. *Truncation (core bug).* `contextlist_base::set_contextids()` is `array_unique()` without a reindex and its
   iterator stops at the first key hole, so `api::get_approved_contextlist_collection_for_collection()` never
   approves (or deletes, or exports) the contexts after it. Sources: logstore_standard, report_stats, core_course,
   and the manager's own shared activity-completion add for every `mod_*` (mod_quiz, mod_scorm).
   Draft MDL report: `docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md`.
2. *Completion gap (core gap).* core_course finds course contexts only through `course_completion_criteria`, so
   `course_completions` rows in courses without (or with untouched) criteria are never found.

Nitin approved Option A on 2026-11-13: a supplementary provider, no core edit (ADR-033).

**What.** `classes/local/erasure_completeness.php` (flag-independent; only the provider reads flags):
- `plan($userid, $truncation, $completion)` copies the manager's discovery per component (provider list,
  `set_component()`, `helper::add_shared_contexts_to_contextlist_for()`), computes full / iterated (from the keys,
  no side effects) / missed, plus the completion gap. Never throws; int userid only.
- The provider adds own + supplementary ids in ONE `add_from_sql`, so its own list has no hole; the stock approval
  then judges those contexts like any other (with the data registry filter on the request path).
- `apply_delete()` hands each dropped context back to its owning component in a dense `approved_contextlist`
  (plus `helper::delete_data_for_user()` for `mod_*`), and the completion gap to core_course. Plan cached from
  discovery: missed ∩ approved; cache miss: before-self components full ∩ approved, after-self missed ∩ approved.
  With registry defaults set, contexts that are not expired-or-unprotected are kept (protects the expiry path).
  Failures are collected; the provider throws ONE `erasuresupplementfailed` exception naming the components.
- `apply_export()` mirrors the manager's export of one list; skipped when `allowfiltering` is set.
- `stock_approval_truncates()` self-check: the truncation part becomes a no-op the day core is fixed.
- Event `erasure_supplemented` (crud d, system context, objectid = the PROCESSING DELETE request, no
  relateduserid) with per-component counts; one mtrace line in CLI/cron.
- `get_metadata()` declares `link_subsystem('core_privacy', ...)` so the plugin registry explains the role.
- Own export walks the ids (avoids core's `get_contexts()` wrong-key unset).

**Flags.** Both default OFF; with both OFF the provider behaves exactly as 1.0.3. Global scope only.

**Tests.** `tests/erasure_completeness_test.php` (@group privacy_erasure): forced-hole unit layer; flags-OFF
canary; full request lifecycle with flags ON (message/e-mail counts equal to flags OFF, event counts); mod_quiz
shared completion hole; completion gap; data registry protected vs expired course (request and expiry paths);
failure reporting (one observer call, one aggregate exception, the rest still runs); missing/deleted subjects;
core canary (`stock_approval_truncates()` must stay true until core is fixed); export with/without
allowfiltering. CI: the group is added as a blocking step next to tenant_isolation.

**Known residuals (recorded, not built).**
- Partial course-context expiry (userlist path): core_completion's `get_users_in_context` needs criteria joins, so
  `course_completions` rows in courses without criteria survive it.
- The helper cleanup the manager runs on this plugin's approved list deletes the subject's
  `course_modules_viewed` rows in any approved module context, including cmv-only ones stock would never discover
  (the subject's own data in an approved context).
- Not reached by any provider: rows in contexts that no longer exist (5,727 in A4), rows of subjects with no user
  row (713 log rows, 229 subjects), log rows where the subject is only relateduserid/realuserid (kept by design).
- Row order: which context the stock path drops depends on the database's row order; the supplement recomputes
  it in the same way, and the post-erasure residue check is the backstop.

**Open (Nitin's call).** Flag ON on UAT/live only after a recorded DPO retention decision and a residue check;
a cutover-runbook item; a residue-sweep CLI for requests already completed with the flags OFF; adding
`erasure_supplemented` to `local_sentientia_platform\audit_log::SENSITIVE_EVENTS`; the A4 harness fixes
(failure bucket, log blast scope) before an A/B re-run on the restored pre-A4 dump. (2026-11-17: the harness fixes are
DONE and the read-only dry run has run; see the 2026-11-17 entries below.)

## 2026-11-16 - 1.1.0 reviewed against the ADR-035 design and re-tested (no version change)

The 11-13 builder stopped before it reported, so a second builder re-read every 1.1.0 file line by line against
the design. Corrections:
- `erasure_completeness::registry_filter()` recorded the literal `'registry'` as the exception of a failed data
  registry check; it now records the exception class, as every other failure entry does
  (`{component, phase, exception class}`).
- The class docblock now names ADR-035 (it cited only ADR-033).
No other change: behaviour, strings, flags and version (2026111300, already installed locally) are as built on
11-13. Both trees byte-identical; `check-tree-drift.php` OK; the served copy equals the ME tree (it also has an
empty `classes/task/` folder from an earlier deploy, harmless).

**PHPUnit init without init.php.** Since 2026-11-16 cmd.exe is disabled on this box, and `init.php` runs every
step through `passthru()` (cmd.exe), starting with `composer self-update`, a network download. Its database steps
were run directly instead: `php public/admin/tool/phpunit/cli/util.php --drop`, `--install`, `--buildconfig`
(about 30 min; composer skipped, vendor/ unchanged). For speed, `innodb_flush_log_at_trx_commit` was set to 2 at
runtime (was 1; it reverts on a MariaDB restart).

**Results, Moodle 5.1.3+ (XAMPP), flags OFF except inside erasure_completeness_test:**
- `tests/erasure_completeness_test.php`: OK, 12 tests, 96 assertions.
- `privacy_manager_test` (3 tests), `erasure_scope_test` (8), `admin_scope_test` (5): OK.
- `local_sentientia_platform` `feature_flag_category_label_test` (8) and `privacy_coverage_test` (2): OK (one
  notice: proctoring's metadata debugging, not this plugin).
- Core `privacy/tests/privacy/provider_test.php`: 1,769 tests, 1 error and 4 failures, none in this plugin
  (content_market null-provider reason; block_learnerscript userlist and table coverage;
  local_sentientia_courses `tenant_share.shared_by`; paygw_airpay table coverage). Every
  local_sentientia_privacy data set passes, including `test_metadata_provider` with the `core_privacy` link.
- `tool_dataprivacy_testsuite`: 323 tests, 3 errors, all in `metadata_registry_test` and none in this plugin
  (the same content_market null-provider reason; the notices are proctoring, recompletion and tool_tcpdffonts).
- `core_privacy_testsuite` without provider_test (`--exclude-filter 'provider_test::'`): OK, 391 tests, 869
  assertions (one notice, in `manager_test`: proctoring and recompletion metadata debugging).
Not run on 11-16: the local 5.3 runtime (`D:/Claude Local/moodle53`, still not run) and the read-only `plan()`
dry run over the 286 A4 subjects (ADR-035 Proof; it ran on 2026-11-17, see below). Logs:
`D:/Claude Local/kit-scratch/1113/erasure-build/1116/`.

## 2026-11-17 - read-only proof, then review fixes (1.1.0, no version change, uncommitted)

**Read-only proof (01:47-02:18 IST, harness in `tools/privacy-erasure-rehearsal/`).** `a4_supplement_plan.php`
planned all 515 A4 subjects in a READ ONLY, rolled-back session: of the 16,844 leftover rows in scope, 704 would go
with a plain stock re-run today, 16,140 only through the supplement, **0 uncovered**; 0 plan failures; nothing
written. Two `a4_sweep.php` dry runs (286 subjects) agreed: stock 726, supplement 16,493, uncovered 0. The A4
harness fixes (failure bucket, blast scope, `get_contextids()` re-discovery, cmc/cmv specs, manifest list) are done.

**Review fixes (03:00 IST onwards, after a read-only review of the 11-13/16/17 work).** No version change: no DB or
capability change, and the code is not committed yet.
- *DPO failure message.* The one string `erasuresupplementfailed` told the DPO to "run it again", which cannot happen
  (the request still ends Deleted and stock never reprocesses status 10), and called an export failure an erasure.
  Now `erasuresupplementfailed_delete` (record the subject for the ADR-035 residue sweep) and
  `erasuresupplementfailed_export` (the export file is missing that data; create a new export request). en/hi 37/37.
- *Audit counts.* `erasure_supplemented` and the CLI `mtrace` line counted attempted contexts as deleted. The event
  now carries `completion_failed`; the description and the mtrace line count as erased only components with
  `failed` = 0 (and the completion courses only when `completion_failed` = 0) and report the rest as attempted.
- *CLI path.* `apply_delete(..., 'cli')` now runs the manager's shared completion cleanup on the approved ids itself
  (cmc/cmv of every approved module context, registry-filtered when defaults are set). On the request and expiry
  paths the manager runs it, so nothing changes there. `a4_sweep.php` no longer makes that call itself.
- *Export under `allowfiltering`.* The whole supplementary export was skipped; now only the truncation part is
  (stored lists are full), and the completion gap is still exported (only this plugin's stored list carries it).
- *Wording.* README, class, provider, version.php and test docblocks no longer say the erasure becomes "complete"
  without the ADR-035 qualifier; the README has a "What still remains" list.
- *Tests.* (3) now proves the supplementary `mod_quiz` dispatch itself (the manager's shared cleanup alone would
  also remove the rows); (6) checks the DPO message and the event's failed flag; new: a failing completion delete,
  the cache-miss branch (frozen clock past the 600 s TTL: `recomputed`, before/after rules), the completion export
  under `allowfiltering`, and the CLI shared cleanup. Four assertions compared `SYSCONTEXTID` strictly with int ids:
  in a process where it is defined from a DB record it is the string `'1'` (it was an int in the 11-16 run), so they
  failed on the first 11-17 run; now `(int) SYSCONTEXTID`. Test-only; the code casts every id through `dense()`.
- *Sweep harness.* `a4s_plan_vs_discovery()` now also compares the iterated split (the stock pass deletes the
  stock-iterated ids, the supplement the plan's missed ids) and a mismatch is a tripwire before any delete under
  `--execute`.

**Deploy.** Both trees byte-identical (`check-tree-drift.php` OK); served copy updated and caches purged (02:59 IST);
no upgrade (version unchanged). Flags OFF; no cron, no flag change, nothing committed.

**Results after the fixes (Moodle 5.1.3+, XAMPP; logs `D:/Claude Local/kit-scratch/1113/erasure-build/1117fix/`):**
- `erasure_completeness_test.php`: OK, 16 tests, 134 assertions (the first run failed 3 on the `SYSCONTEXTID` type
  trap above).
- `privacy_manager_test` (3), `erasure_scope_test` (8), `admin_scope_test` (5): OK.
- Core `provider_test.php`: identical to 11-16 (1,769 tests, 1 error and 4 failures in other plugins, 134 risky from
  proctoring/recompletion/tcpdffonts debugging output).
- Dry runs re-run (read-only): `a4_supplement_plan.php` and `a4_sweep.php` counts identical, 0 uncovered, 0 subjects
  whose plan splits a list differently from the stock discovery.
Still not run: the local 5.3 runtime suites.

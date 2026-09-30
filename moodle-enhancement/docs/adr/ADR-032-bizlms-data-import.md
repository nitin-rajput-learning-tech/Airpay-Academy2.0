# ADR-032 — Import BizLMS feature data through one shared framework

**Status:** Proposed (2026-09-29), import decided by Nitin 2026-09-29
**Decides:** how the history held in the 22 BizLMS plugins' tables becomes normal Sentientia history at cutover
**Evidence:** `docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` §3.3 (lines 136-177) and I-list (lines 51-56);
`docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` (per-feature maps, verified, corrections applied)
**Related:** ADR-031 (tenant scope fails closed), ADR-022/025 (component renames)

Path roots used below: **SE** = `moodle-enhancement/`, **TOP** = repository root (`D:/Claude Local/airpay-ld-os/`),
**BZ** = `D:/Claude Local/Moodle Backup/01-production-codebase/html/` (production 4.1.2 snapshot).

## Context

- Production runs Moodle 4.1.2 with 22 purchased eAbyas BizLMS plugins. Cutover restores the live
  database, upgrades it 4.1.2 -> 4.5 -> 5.2, and deploys the Sentientia plugins. BizLMS code is not
  deployed. Its tables stay in the database.
- The 2026-09-29 audit found that **82 of the 93 BizLMS tables are neither read nor copied by any
  Sentientia code** (migration plan :138-145). Classroom rosters and attendance, program and
  learning-plan progress, evaluation answers, cart orders and ledger, recompletion evidence, requests,
  skills history, HR-sync history, ratings and email logs are on disk but invisible after cutover.
  The plan calls this a Stage B blocker until import vs archive is decided (:54-56).
- **Nitin decided on 2026-09-29: import all BizLMS feature data**, so learners, admins and auditors see
  it as normal history.
- The three existing copy scripts cannot be reused:
  - `SE local/sentientia_org/cli/migrate_all.php` skips any target that has rows (:244-251), silently
    drops columns it cannot map (:269-277), loads the whole source table into memory (:280), and calls
    `reset_sequence` inside the open transaction (:300-301). On MySQL that is DDL, which commits
    implicitly, so the rollback at :315 cannot undo the rows already written.
  - `SE local/sentientia_org/data_migration.php` has the same skip rule (:47-53), reads a source column
    that does not exist (`theme_scheme`, :81), copies a vancode string into an INT sort order (:82),
    overwrites the source `timemodified` (:84), and resets the sequence before commit (:93, :96).
  - `SE local/sentientia_core/cli/backfill_org.php` covers only the org model.
- Three Sentientia readers fall back to a legacy table while their own table is empty:
  `session_manager.php:110-134` (classroom), `path_manager.php:348-358` (learning path) and
  `rating_manager.php:42-46` (ratings). A partial import turns those fallbacks off silently.
- The local copy of production has none of the large tables (migration plan :163-168). Production row
  counts are unknown (input I-20). Every design choice below must hold for tables of unknown size.
- Two framework drafts were judged: **Design A (fidelity-first)** and **Design B (operations-first)**.
  Design B is the base. Nine parts of Design A are grafted in. The judgement is in "Alternatives
  rejected".

## Decision

1. **One framework, one importer per feature.** The framework lives in `local_sentientia_platform`
   (both trees). Each feature's importer lives in the plugin that owns its target tables, is declared
   in that plugin's `db/bizlms_import.php`, and is discovered the way `db/feature_flags.php` files are
   (`SE local/sentientia_platform/classes/feature_flags.php:441-458`). An importer never writes
   another feature's tables.
2. **Legacy tables are read-only and are the archive.** The importer never writes, updates or deletes
   a BizLMS table, and never deletes any row anywhere. Values that no Sentientia reader, engine or
   planned reader uses stay in the legacy table and are not copied. No BizLMS plugin may be uninstalled
   on the target (migration plan :169-173). Dropping the legacy tables later needs its own ADR.
3. **One shared map is the only idempotence key.** Every source row gets exactly one primary row in
   `local_sentientia_legacymap`. The per-feature `legacy_id`, `legacy_ref`, `legacykey` columns and the
   `local_sentientia_classroom_legacy` archive table proposed by the maps are not built.
4. **Ids: always resolve through the map; keep legacy ids only where declared.** A step keeps legacy
   ids only when rows the import does not rewrite store those ids. If a kept id is taken, the feature
   stops. It never falls back to a new id (section "Id strategy").
5. **The import is gated by a CLI guard, not a feature flag.** It has no user-visible surface. Every
   new reader surface that shows imported history ships behind its own default-OFF flag.
6. **Users never see a half-imported feature.** The site stays in maintenance for the window. A
   per-feature completion marker is written only after verify and finalise. A status check reports
   CRITICAL while any applicable feature has started but has no marker. The three reader fallbacks
   are removed in the same release as the importer of their feature.
7. **Parity is extended** with legacy-table fingerprints, a per-step accounting identity, an
   unclaimed-table check and each importer's own verify.
8. **Framework tables hold no personal data.** Personal data lands only in feature target tables, and
   each is declared in that plugin's privacy provider.
9. **Owner choices are data, not defaults.** A checked-in decisions file holds every owner choice.
   A missing required choice blocks the feature. Its hash is pinned from the rehearsal to cutover.
10. **Every importer ships the contract tests** and its feature tests before it may run on Stage B.

## Components

```
local/sentientia_platform/                        (both trees)
  classes/bizlms/
    importer.php, step.php, recompute_step.php     contracts
    outcome.php, reason.php, decision.php, source_spec.php
    context.php, lookups.php, legacy_reader.php    read-only access for steps
    legacymap.php, provenance.php                  map API and runtime "is imported" guard
    writer.php                                     the only code that writes
    runner.php, registry.php                       lifecycle, discovery, dependency sort, locking
    tenant_resolver.php, text.php, file_rehome.php helpers
    sideeffect_guard.php, fingerprint.php          tripwire and source fingerprints
    report.php, parity.php, unclaimed.php
  classes/check/bizlms_import.php                  core status check
  classes/phpunit/legacy_schema_fixture.php        test trait
  classes/phpunit/importer_contract.php            test trait
  cli/import_bizlms.php                            new
  cli/migration_parity_check.php                   extended (both copies)
  lib.php                                          new in the ME tree: local_sentientia_platform_status_checks()
  db/install.xml + db/upgrade.php + version.php    three framework tables
<each target plugin>/                              (both trees)
  db/bizlms_import.php
  classes/bizlms/*.php
  tests/bizlms_import_test.php
  tests/fixtures/bizlms/<bizlms plugin>.install.xml
```

Precondition: `sentientia_platform/db/install.xml` already differs between the trees and is baselined
(`TOP tools/tree-drift-baseline.txt:46`). An edit to one copy would not trip the drift gate. Reconcile
the two copies first and remove that baseline line in the same commit; the gate fails if a baselined
path is reconciled but still listed (`TOP tools/check-tree-drift.php:51-53`).

## Framework schema

Names are 27 characters or fewer (Moodle 4.5+ allows 53, `TOP lib/xmldb/xmldb_table.php:41,50`).

**`local_sentientia_legacymap`**: one row per source row, plus one per fan-out sub-row.

| Field | Type | Meaning |
|---|---|---|
| id | int(10) seq | |
| feature | char(40) NN | `classroom` |
| sourcetable | char(64) NN | legacy table without prefix. A derived group source is named with a `#` prefix, e.g. `#local_biz_cart_history.identifier`. Core tables read as a source keep their name, e.g. `logstore_standard_log`. |
| sourceid | int(10) NN | legacy id. For a derived group: the group's non-personal integer key (a cart identifier) or the MIN source id of the group. Never a user id. |
| subkey | char(64) NN default `''` | `''` = the primary row. Otherwise a fan-out sub-key such as `quiz:17` or `assign`. |
| targettable | char(64) NN default `''` | `''` for archived and skipped rows (NOT NULL so re-runs cannot create duplicate NULL keys) |
| targetid | int(10) NULL | for merged and folded rows, the surviving target row |
| outcome | char(16) NN | `imported`, `adopted`, `merged`, `folded`, `archived`, `skipped` |
| reason | char(40) NULL | a code from the importer's reason vocabulary |
| detail | char(255) NULL | codes and ids only; never names, emails or free text |
| runid | int(10) NN | |
| timecreated | int(10) NN | |

Keys: `UNIQUE uk_src (sourcetable, sourceid, subkey)` (128 characters plus an int, under the 333-character
index trap recorded after the 2026-06 gap build); `INDEX (targettable, targetid)` for reverse lookup and
the provenance guard; `INDEX (feature, outcome, reason)`; `INDEX (runid)`.

The key deliberately leaves out `targettable`. So one source row has exactly one primary map row, and
the database enforces it. Accounting is exact: source rows = primary map rows.

**`local_sentientia_legacyrun`**: id, mode (`apply`|`purge`), status (`running`|`complete`|`failed`|`aborted`),
features (text, JSON), decisionshash char(64), codehash char(64) (plugin versions involved),
fingerprint char(12), host char(100), pid int, timestarted, heartbeat, timefinished.
No operator user id.

**`local_sentientia_legacystep`**: id, runid, feature, stepkey char(64), sourcetable char(64),
status (`pending`|`running`|`done`|`failed`|`not_applicable`), watermark int NULL, srccount int,
srcmaxid int, srccrc char(20) NULL, counters (processed, imported, adopted, merged, folded, archived,
skipped), error text (message only, never row data), timestarted, timemodified (heartbeat),
timefinished. `UNIQUE (runid, stepkey)`.

None of the three tables has a column named in `USER_COLUMNS`
(`SE local/sentientia_platform/tests/privacy_coverage_test.php:52-63`), so the platform provider does
not need to declare them. Keep it that way.

## Id strategy

1. **Every source row gets exactly one primary map row**, whatever its outcome.
2. **Importers resolve every foreign legacy id through `legacymap::resolve()`, even when the id was
   kept.** No importer assumes target id = source id. Core ids (user, course, course module, quiz,
   context) are not mapped, because the restored database keeps them. `lookups` checks that they exist.
3. **A step is PRESERVE only when both hold:** (a) rows the import does not rewrite store its legacy
   ids, declared in `external_refs()`; (b) its target table holds no seeded rows. Every other step is
   MAP. A framework test fails if a MAP step declares `external_refs()`.
4. **PRESERVE succeeds whole or refuses.** Preflight pages the legacy id set against the target. An
   occupied id that is not an adoptable copy blocks the whole feature. "Keep the id where free, else a
   new id" (the program map) is forbidden: it splits the id space silently.
5. **One adopt rule for every feature.** A target row at the legacy id with no map row is adopted only
   when its id and the step's `adopt_signature()` columns (default `name`, `timecreated`) equal the
   source. That is the fingerprint of a header copy by `migrate_all.php`, which copies at the same id
   (`migrate_all.php:295`). The adopted row is overwritten with the full mapping. Any other occupant is
   a blocker.
6. **Sequences are reset in `finalise()`,** after every batch of the feature has committed, on every
   PRESERVE target. Never inside a transaction.
7. **No remap mode.** A collision is resolved by an operator, not by the importer.

PRESERVE targets and why (details in the mapping doc):

| Feature | Legacy -> target | Rows the import does not rewrite that hold the id |
|---|---|---|
| org | `local_costcenter` -> `local_sentientia_org` | every `open_path` and `costcenterid`; ids already kept 1:1 (`SE local/sentientia_org/data_migration.php:69,86`) |
| classroom | `local_classroom`, `local_classroom_sessions` | `tool_certificate_issues.moduleid` with moduletype `classroom` (`BZ local/classroom/classes/local/general_lib.php:72`); core `{event}.plugin_instance` (`BZ local/classroom/classes/classroom.php:183-205,342-362`); enrol `customint1` (`classroom.php:1781-1786`); `local_evaluations.instance` for plugin `classroom` (`BZ local/classroom/lib.php:139`); rating area `local_classroom` (`SE local/sentientia_ratings/classes/external/submit_rating.php:47`) |
| program | `local_program` | `tool_certificate_issues.moduleid` (`BZ local/program/classes/output/renderer.php:793`); rating area `local_program` (`submit_rating.php:48`); enrol `customint1` (`BZ local/program/classes/program.php:1489-1531`); request `componentid` (`BZ local/request/classes/api/requestapi.php:317-339`); `local_emaillogs.moduleid` (`BZ local/program/classes/notification.php:173,183`) |
| learningplan | `local_learningplan` | certificates (`BZ local/learningplan/classes/render/view.php:2080,2656`); rating area `local_learningplan` (`submit_rating.php:49`); request `componentid` (`view.php:1448`); the `planid = pathid` fallback (`SE local/sentientia_learningpath/classes/path_manager.php:296-303`) |
| evaluation | `local_evaluations` (**changed from the evaluation map**, which planned new ids) | core `{event}` rows with plugin `local_evaluation` (`BZ local/evaluation/lib.php:440,481`); `local_classroom.trainingfeedbackid` and `local_classroom_trainers.feedback_id` (`BZ local/classroom/db/install.xml:31,115`); `local_emaillogs.moduleid` for moduletype `feedback` (`BZ local/evaluation/users_assign.php:171-192`) |
| skills | `local_course_levels` | `course.open_level` ("links to local_course_levels", `SE local/sentientia_courses/classes/course_fields.php:46`) |
| course_lookups | `local_course_types`, `local_custom_category` | `course.open_identifiedas` (`BZ local/courses/lib.php:1272`), `course.open_categoryid` (`BZ local/courses/classes/form/custom_course_form.php:134-153`) |
| cart | order number: identifier -> `local_sentientia_cart_id` | printed receipts `bookingreceipt_<identifier>` (`BZ local/biz_cart/receipt.php:73,198`); core `payments.itemid` (`BZ local/biz_cart/classes/biz_cart_history.php:201-202`) |

Every other table is MAP.

## Importer interface

```php
namespace local_sentientia_platform\bizlms;

/** One import unit. Lives in the TARGET plugin, classes/bizlms/. */
interface importer {
    public function feature(): string;              // 'classroom'; unique across the registry
    public function component(): string;            // 'local_sentientia_classroom'
    public function requires_version(): int;        // plugin version carrying the schema additions
    /** @return string[] feature keys that must be complete or not_applicable first */
    public function depends(): array;
    /** @return array<string, source_spec> legacy tables this feature claims (one owner per table) */
    public function sources(): array;
    /** @return array<string, string> legacy table => why it is deliberately not imported */
    public function declined_tables(): array;
    /** @return string[] the only tables the writer will touch for this feature */
    public function target_tables(): array;
    /** @return array<string, string> core table => reviewed reason (e.g. tag_instance remap) */
    public function core_writes(): array;
    /** @return array<string, string> target table => path/root column, for generic tenant verify */
    public function tenant_columns(): array;
    /** @return reason[] the only codes skip, merge and archive may use */
    public function reasons(): array;
    /** @return decision[] owner choices read from the decisions file */
    public function decisions(): array;
    /** True: run the whole feature in one outer transaction when its preflight total is small. */
    public function atomic(): bool;
    /** @return step[] load steps, then recompute_step[], in execution order */
    public function steps(): array;
    /** Read-only. Blockers stop the whole run; warnings go to the report. */
    public function preflight(context $ctx): preflight;
    /** Read-only, after load and recompute. @return string[] failures (empty = pass) */
    public function verify(context $ctx): array;
    /** Outside any transaction, idempotent: reset_sequence, file copies, cache purges, markers. */
    public function finalise(context $ctx): void;
}

final class source_spec {
    public function __construct(
        public readonly string $table,
        public readonly bool $required = true,    // missing required table: blocker; all missing: not_applicable
        /** @var array<string, array<string|int, string>> column => value => meaning. Unknown values block. */
        public readonly array $enums = [],
        public readonly array $optionalcolumns = [], // production-only columns, e.g. costcenterid
    ) {}
}

final class reason {
    public function __construct(
        public readonly string $code,         // 'orphan_user', 'dup_natural_key', 'not_history'
        public readonly bool $retryable,      // re-attempted by --retry-skipped
        public readonly bool $needsowner,     // parity exits 2 until the decisions file accepts it
    ) {}
}

abstract class step {
    abstract public function key(): string;           // 'classroom.attendance'
    abstract public function sourcetable(): string;   // the accounting unit
    abstract public function targettable(): string;   // primary target
    public function idpolicy(): string { return idpolicy::MAP; }       // or idpolicy::PRESERVE
    /** Rows the import does NOT rewrite that hold this source's ids: [table, column, where][] */
    public function external_refs(): array { return []; }
    public function adopt_signature(): array { return ['name', 'timecreated']; }
    /** Source columns forming one dedupe or fold group; [] = one row per group. */
    public function group_by(): array { return []; }
    public function columns(): array { return ['*']; }
    /** Extra WHERE on the source: [sql, params]. Portable SQL only. */
    public function source_filter(): array { return ['', []]; }
    /** Map pairs to preload before transforming: [[sourcetable, subkey], ...] */
    public function preload(): array { return []; }
    /**
     * PURE. The rows of ONE group (ordered by id) in, outcomes out. No DB writes.
     * Reads only through $ctx. Dry run and apply run the same code.
     * @param \stdClass[] $rows
     * @return outcome[]
     */
    abstract public function transform(array $rows, context $ctx): array;
}

/** Second pass over rows this run imported, e.g. program currentlevelid, credit balances. */
abstract class recompute_step {
    abstract public function key(): string;
    abstract public function targettable(): string;
    /** @param int[] $targetids @return outcome[] (update only) */
    abstract public function recompute(array $targetids, context $ctx): array;
}

final class outcome {
    public static function insert(int $sourceid, string $table, \stdClass $row, string $subkey = ''): self;
    public static function adopt(int $sourceid, string $table, int $targetid, \stdClass $fields): self;
    public static function merge(int $sourceid, int $winnersourceid, string $reason): self;
    public static function fold(int $sourceid, string $table, int $targetid, string $reason): self;
    public static function archive(int $sourceid, string $reason): self;   // stays only in the legacy table
    public static function skip(int $sourceid, string $reason, string $detail = ''): self;
    /** Only rows this importer created or adopted. */
    public static function update(string $table, int $targetid, \stdClass $fields): self;
    public function warn(string $code): self;           // 'truncated:name', 'derived_timestamp', 'url_sanitised'
    public function tenant_method(string $method): self; // exact|normalised|walked_up|fallback:<name>|unresolved
}

final class context {
    public readonly bool $dryrun;
    public readonly int $runid;                 // 0 in dry run
    public readonly legacymap $map;             // in dry run, an in-memory overlay
    public readonly tenant_resolver $tenant;
    public readonly lookups $lookups;           // users, courses, orgs: bulk-loaded, read-only
    public readonly legacy_reader $legacy;      // bounded, read-only access to other legacy tables
    public readonly text $text;                 // fit(): explicit truncation that reports
    public function decision(string $key): mixed;   // blocker if unset and no default
    public function servertz(): \DateTimeZone;      // Moodle's effective server timezone
}

final class legacymap {
    public function resolve(string $sourcetable, int $sourceid, string $subkey = ''): ?int;
    /** @return array<int, int|null> chunked at 1000 ids per query */
    public function resolve_many(string $sourcetable, array $sourceids, string $subkey = ''): array;
    public function preload(string $sourcetable, string $subkey = ''): void;
    public static function feature_complete(string $feature): bool;
}

final class provenance {
    /** Runtime guard for readers: [sql, params] matching target rows NOT created by the import. */
    public static function not_imported_sql(string $alias, string $targettable, string $tag = 'blm'): array;
    public static function is_imported(string $targettable, int $targetid): bool;
}

final class tenant_resolver {
    /** ' 1/5/ ', '//1//5', '/1/5' -> '/1/5'; '', '0' and non-digit segments -> null */
    public static function normalise(?string $raw): ?string;
    /** @param array<string, string|int|null> $candidates ordered name => path or root */
    public function resolve(array $candidates): array;     // [path, root, method]
    public function org_for_path(string $path, bool $walkup = true): ?\stdClass;
    public function root_of_user(int $userid): int;
}
```

Registry file, one per target plugin:

```php
<?php
// local/sentientia_classroom/db/bizlms_import.php (both trees)
defined('MOODLE_INTERNAL') || die();
$imports = ['classroom' => \local_sentientia_classroom\bizlms\importer::class];
```

`registry::load()` walks installed plugins without the MUC cache (CLI only). It refuses to run when a
feature key is duplicated, a legacy table is claimed or declined by two importers, a dependency is
unknown or cyclic, a class does not implement `importer`, or an installed plugin is below
`requires_version()`. Tests register toy importers through `registry::set_testing_importers()`.

## Writing rules

The writer is the only code that writes. It enforces:

1. **Declared tables only:** `target_tables()` plus reviewed `core_writes()`.
2. **No silent column loss.** It refuses a field that is not a target column. `import_record` would
   silently skip it (`TOP lib/dml/mysqli_native_moodle_database.php:1639-1642`); that is how
   `migrate_all.php:269-277` lost columns.
3. **No strict-mode aborts in a batch.** It refuses a missing value for a NOT NULL column with no
   default, a char value longer than the column's `max_length`, and a non-integer for an int column.
   A step that must shorten text calls `$ctx->text->fit()`, which truncates with `core_text::substr`
   and adds a `truncated:<column>` warning. The full value stays in the legacy table.
4. **Source timestamps are kept.** Every `time*` column of the target must be set explicitly.
   `time()` is not allowed, except where the mapping doc names a target with no source timestamp.
5. **PRESERVE** writes use `import_record` with `->id` = the legacy id; the writer asserts it.
   MAP writes use `insert_record`. Map rows are bulk-inserted with `insert_records`; targets holding
   TEXT bodies or JSON are written row by row.
6. **Unknown enum values never reach a target.** Preflight prints a histogram of every declared enum
   column. A value missing from `source_spec::enums` blocks the feature until the decisions file maps it.

## Reading and performance

- `get_recordset_sql()` on MySQL buffers the whole result (`MYSQLI_STORE_RESULT`,
  `TOP lib/dml/mysqli_native_moodle_database.php:1301-1302`). So the framework owns every source read
  and pages it by keyset: `WHERE id > :watermark ORDER BY id`, with a LIMIT. Steps never open
  recordsets.
- **Grouped steps** (dedupe and fold units) run in two phases, because legacy tables cannot be given
  new indexes: (1) scan `id` plus the group columns in PK pages and build `group -> [ids]` in memory;
  (2) process groups ordered by their **minimum source id**, fetching each batch by `id IN (...)`.
  The resume watermark is that minimum id. Keyset paging on a string group key is banned.
  `--max-group-scan` caps phase 1.
- Users, courses, orgs and declared map pairs are preloaded into integer arrays. No per-row SELECT.
- The report records rows per second per step. Stage B timings set the cutover window.

## Transactions

- **Batch mode (default).** One delegated transaction wraps a batch's target writes, its map rows and
  the step's watermark and counters. A crash rolls back the batch; the watermark stays; `--resume`
  continues. The map's unique key blocks a double insert if a watermark ever lags.
- **Feature mode (graft from Design A).** When `atomic()` is true and the preflight source total is at
  or below `--atomic-threshold` (default 50 000 rows), the runner wraps the whole feature in one outer
  delegated transaction. A crash leaves nothing. Features expected to be small declare `atomic()`
  (list in the mapping doc, section 2). Above the threshold they run in batch mode and the operator is
  told.
- **No DDL inside a transaction.** `reset_sequence()` issues `ALTER TABLE ... AUTO_INCREMENT` on
  MySQL (`BZ lib/ddl/mysql_sql_generator.php:109-111`), which commits implicitly. It runs only in
  `finalise()`, after the outermost commit. The runner asserts `!$DB->is_transaction_started()` first.
  Schema additions ship as plugin versions; preflight checks those versions.
- **Visibility.** `--apply` needs maintenance mode. The marker
  `local_sentientia_platform/bizlms_complete_<feature>` = runid is written in `finalise()` after verify.
  The status check reports CRITICAL while an applicable feature has started without a marker. Runbook
  rule: the site does not leave maintenance until `admin/cli/checks.php` is clean.

## Side-effect safety

1. **Pure transforms.** Steps return outcomes; they have no write API.
2. **The writer** (section "Writing rules").
3. **Tripwire.** Before and after each feature the framework reads `MAX(id)` of append-only tables:
   `logstore_standard_log`, `messages`, `notifications`, `task_adhoc`, `event`, `user_enrolments`,
   `role_assignments`, `course_completions`, `course_modules_completion`, `quiz_attempts`,
   `badge_issued`, `tool_certificate_issues` (if present), `local_sentientia_evaluation_triggers`,
   `local_sentientia_notif_log`, `local_sentientia_email_log` (except for the feature that targets it),
   plus the importer's own extra list. Any change outside declared targets and core writes aborts the
   run before the next feature. `MAX(id)` is O(1) and catches inserts; updates to state tables are
   caught by the existing parity checksums at the end (`migration_parity_check.php:116-133`).
4. **Static scan.** A test tokenises every `*/classes/bizlms/*.php` and fails on: `message_send`,
   `email_to_user`, `->trigger(`, `role_assign(`, `enrol_user`, `enrol_try_internal_enrol`,
   `completion_completion`, `mark_complete`, `cohort_add_member`, `core_tag_tag::`, `update_course(`,
   `calendar_event::create`, `get_recordset`, `get_records(` outside the framework, `reset_sequence`
   outside `finalise()`, any `$DB->` write method, and the managers the maps forbid
   (`session_manager::`, `waitlist_manager::`, `path_manager::`, `program_manager::`,
   `request_manager::`, `cart_manager::`, `invoicer::`, `notifier::`, `delivery_log::log`,
   `evaluation_manager::submit_response`, `recompletion_engine::`, `skills_manager::`,
   `rating_manager::submit_rating`).
5. **Environment:** `$CFG->noemailever`, the task runner off, and tests that wrap every import in
   `redirectEvents()`, `redirectMessages()` and `redirectEmails()` and assert all three are empty.

## CLI

```
php local/sentientia_platform/cli/import_bizlms.php
    --status                         fingerprint, guard state, per-feature state, heartbeats
    --list                           features, owners, dependencies, source presence, unclaimed tables
    --preflight                      read-only, selected features; prints enum histograms and blockers
    --feature=a[,b] | --all          dependencies are added and sorted automatically
    (default) dry run                writes nothing to the database, not even bookkeeping
    --apply                          needs every guard below
    --resume                         continue the latest incomplete apply run from its watermarks
    --retry-skipped=<reason>[,..]    re-process rows skipped with a retryable reason
    --verify                         run verify and parity checks only
    --decisions=FILE                 owner choices; its sha256 is stored on the run
    --expect-decisions-hash=SHA256   cutover must use the rehearsed decisions
    --report=FILE                    JSON, plus FILE.csv of non-imported rows
    --batch=500 --atomic-threshold=50000 --max-group-scan=2000000
    --confirm=<fingerprint>          required with --apply and --purge-feature
    --allow-online                   skip the maintenance requirement (rehearsal only)
    --purge-feature=<key> --i-understand-this-deletes
                                     rehearsal only; deletes only rows whose map outcome is
                                     'imported'; refuses when the feature has adopted rows or rows
                                     changed after import; [CONFIRM] per CLAUDE.md
```

- In a `--all` dry run, upstream features fill an in-memory map overlay (MAP steps get negative
  virtual ids), so dependents resolve against it. A single-feature dry run whose dependencies were not
  applied reports those rows as `deferred`, not as failures.
- **Exit codes:** 0 = done, or nothing applicable; 1 = blocker, failure, collision or source drift;
  2 = done but unproven (needs-owner reasons not accepted, or unclaimed legacy tables holding rows);
  3 = a guard refused. 0, 1 and 2 match `migration_parity_check.php:19-25`.
- **Report JSON:** per run, feature and step: source count, max id and CRC; counts per outcome;
  skipped per reason; warnings; tenant method counts; rows per second; tripwire result; verify
  failures; the decisions used. **Ids and codes only**: reports leave the database, so they carry no
  personal data.

## Gating

The import is a one-time, whole-site data operation with no user-visible surface. The CLAUDE.md flag
rule (§5, §13) is about user-visible features. A flag would be the wrong control: the Switchboard's
tenant and customer scopes mean nothing here, an admin can flip a flag from a web page, and
`feature_flags::set()` is a toggle while an import is an event. So the gate is a CLI guard.
`--apply` refuses (exit 3, naming the missing condition) unless **all** of these hold:

1. `CLI_SCRIPT`, and `--confirm=<fingerprint>` equal to the first 12 hex characters of
   `sha1($CFG->wwwroot . '|' . $CFG->dbname . '|' . $CFG->prefix)`, printed by `--status`. A command
   copied from a rehearsal cannot run on production.
2. `local_sentientia_platform/bizlms_import_armed_until` is later than now. The operator sets it with
   `admin/cli/cfg.php` (runbook: now + 4 hours). It expires on its own. A clean `--all` run clears it.
3. CLI maintenance mode is on, unless `--allow-online` is given. `--allow-online` and `--purge-feature`
   are refused when `local_sentientia_platform/bizlms_production = 1`, which the cutover runbook sets.
4. `$CFG->noemailever` is true.
5. The scheduled-task runner is off (`cron_enabled` = 0; confirm the setting name on 5.2) and no task
   lock is held. The tripwire also detects a leak.
6. The lock `bizlms_import` is taken through the core lock API. It is released if the process dies;
   heartbeats in `legacystep` show a crashed run.
7. Every target plugin is at or above `requires_version()`.
8. Every decision the selected features need is present in `--decisions`; at cutover, its hash equals
   `--expect-decisions-hash`.
9. Preflight found no blocker.

**Reader surfaces.** Each feature's new or changed reader surface (learner history pages, evidence
views, review lists, detail views) ships behind a default-OFF flag registered in its plugin's
`db/feature_flags.php`. The import never flips a flag. Whether the reader flags are ON for the Airpay
customer at cutover is Nitin's decision (BizLMS showed most of this history to users today).

**Fallback readers.** The legacy fallbacks in `session_manager.php`, `path_manager.php` and
`rating_manager.php` are removed in the same release that ships their feature's importer; no user runs
Sentientia on a restored BizLMS database today, and the fallbacks carry known defects (for example
`session_manager.php:78` sorts the legacy sessions table by a column it does not have). The completion
marker is a recorded fact used by the status check, dependency ordering and `feature_complete()`;
it is not a toggle.

## Parity hooks

All in `local/sentientia_platform/cli/migration_parity_check.php`, in both trees.

1. **Legacy fingerprints in the baseline.** The baseline is taken on the restored 4.1.2 copy before the
   core hops, as the script's usage says (:13-17); nothing is written on live. For every table from
   `$DB->get_tables()` matching `local_%`, `block_%` or `paygw_%`, excluding `local_sentientia_%`:
   row count, `MAX(id)` and a CRC over **all** columns sorted by name, built like the current checksums
   (:158-173). The column list is stored, so a column change counts as drift. CRC is null on
   non-MySQL engines, as today (:151-156). This doubles as the I-20 inventory, and it sees tables no
   snapshot install file declares. On `--compare`, every legacy table must be present and identical:
   that proves the two hops and the import left the archive untouched. A missing table is drift.
2. **Invariant `bizlms_import`** added to `sentientia_parity_invariants()` (:193-198), behind a
   `class_exists` guard like the existing one (:194-196). It returns `[]` when no legacy tables exist
   (a fresh install). Hard problems (exit 1):
   - an applicable feature without its completion marker;
   - per step, `source rows (with the step filter) != primary map rows`; for in-place core steps
     (the tag remap), the preflight snapshot count is used and verify asserts the filter now matches 0;
   - a source-to-map anti-join finds a row with no map row;
   - a map-to-target anti-join finds an `imported` or `adopted` row whose target is gone
     (checked until the runbook sets `local_sentientia_platform/bizlms_production_open` when the site
     opens, since admins may delete rows after go-live);
   - a `tenant_columns()` value that is neither NULL nor a valid path with a registered root
     (`tenant::assert_valid`, `SE local/sentientia_platform/classes/tenant.php:191-199`);
   - a legacy table's fingerprint differs from the one stored on the run (mutated after import);
   - any `importer::verify()` failure.
3. **Unproven items** (exit 2): unclaimed legacy tables holding rows, and needs-owner reasons the
   decisions file has not accepted. Unclaimed = database tables, minus the installed `install.xml`
   schema, minus Sentientia runtime tables listed in `classes/schema/*::TABLES`, minus every table an
   importer claims or declines. This catches tables whose plugin code is missing from the snapshot
   (`local_challenge`, `local_positions`, `local_domains`, `block_request_*`).
4. The existing core counts and checksums (:52-76, :116-133) stay the proof that the import had no
   side effects on users, enrolments, completions, attempts, badges and grades.

## Privacy

- The framework tables hold no personal data. `detail` holds codes and ids only. Reports hold ids
  and codes only.
- Every new target column that names a person is declared in its plugin's privacy provider, with en
  and hi strings. `USER_COLUMNS` (`privacy_coverage_test.php:52-63`) gains every actor-column name the
  mapping doc introduces (`enrolledby`, `markedby`, `initiatedby`, `sender_userid`, `subject_userid`),
  so the structural guard sees them. A plugin whose provider then fails (for example
  `sentientia_users`, a null provider today) fixes its provider in the same change.
- Secrets are never copied. The plaintext passwords in BizLMS welcome emails are the known case: those
  bodies and subjects are not imported (notifications section of the mapping doc).
- **Known gap, not solved here:** once BizLMS code is off disk, the legacy tables hold learner data
  that no provider exports or erases. The import never alters them. A separate, Nitin-gated deliverable
  after sign-off adds a platform provider section that exports legacy rows by user and anonymises them
  under the DPDP design. Declaring them without erasure would repeat the null-provider defect class.

## Test approach

1. **Fixture trait** `\local_sentientia_platform\phpunit\legacy_schema_fixture`:
   `create_legacy_tables(string $fixturexml, array $only = [], array $extrafields = [])`,
   `truncate_legacy_tables()`, `drop_legacy_tables()`. It refuses unless `PHPUNIT_TEST` is set and
   `$CFG->prefix === $CFG->phpunit_prefix`. It loads a checked-in XMLDB copy and creates tables one at a
   time, dropping a leftover from a killed run first. Lifecycle (graft from Design A, for speed on the
   slow local MariaDB): create once in `setUpBeforeClass()`, truncate in `setUp()`, drop in
   `tearDownAfterClass()` inside `try/finally`. DDL is never run inside a transaction.
2. **Fixture copies** `tests/fixtures/bizlms/<plugin>.install.xml`: verbatim copies of the BZ file with a
   header giving source path, sha1 and date. Only three edits are allowed, each commented with the
   source line: (a) strip PREVIOUS/NEXT; (b) replace types XMLDB rejects (e.g. `local_request_comments.dt`
   `datetime` -> char(20)); (c) add production-only columns and install.php-only tables in production
   shape, with production NOT NULL constraints.
3. **Framework tests** (`SE local/sentientia_platform/tests/bizlms/`) use a toy importer:
   dry run writes nothing; apply satisfies the accounting identity and keeps source timestamps;
   a second apply writes nothing; a failpoint at batch k then resume gives the same rows as a clean
   run; a PRESERVE collision blocks and an identical header row is adopted; after finalise a native
   insert gets an id above the legacy maximum; no legacy tables gives `not_applicable` and exit 0 even
   with `--apply`; a source change between runs makes resume refuse; a test-only logstore write trips
   the tripwire; the writer rejects an unknown field, an overlong char and a missing timestamp;
   interleaved groups are processed deterministically; the marker is set only after verify; the static
   scan; the one-owner rule; an unknown enum value blocks.
4. **Contract trait** `importer_contract` (graft from Design A), used by every feature test with that
   feature's seed: not applicable without tables; dry run writes nothing; apply reconciles; second
   apply is a no-op; resume after an injected failure; source change detected; collision blocks and
   header row is adopted; preserved ids; no side effects (sinks and tripwire); privacy export and erase
   for every new user column.
5. **Feature tests** follow the fixture section of each feature in the mapping doc, including reader
   checks as a tenant admin in `@group tenant_isolation` (ADR-031 decision 8). All import tests are
   `@group bizlms_import` and run from the moodle5 dirroot, not `public/`.
6. MySQL 8 and MariaDB are the gating engines; production is MySQL 8.0.44 on RDS (CLAUDE.md §2).
   Import SQL must still be portable `$DB` API SQL (no `GROUP_CONCAT`, `FIND_IN_SET`, `REGEXP`,
   `INSERT IGNORE`, `ON DUPLICATE KEY`; CSVs are split in PHP). A PostgreSQL run is not a gate.

## Build and run order

**Build:** Phase 0 is the framework plus source freezing (below). The framework is frozen after
Phase 0; changing its contract needs an amendment to this ADR. Then features, each one deliverable,
in dependency order: org; then org_roles, cohort_scope, course_lookups, course_tags, legacy_logs,
exams, users, notifications, recompletion, cart (parallel); skills; classroom and program; learningplan;
evaluation, request and ratings; then the gap maps.

**Phase 0 source freezing:** move `SE local/sentientia_pages/qr_scan.php` and `qr_attendance.php` off
the legacy tables (they check and write `local_classroom_attendance`, migration plan :174-177); make
`migrate_all.php` and `data_migration.php` refuse and point to the new CLI; block
`SE local/sentientia_pages/cli/setup_costcenters.php`, `setup_bizlms_data.php` and
`fix_all_bizlms_data.php` on a database that holds legacy tables.

**Cutover slice** (after the two core hops):
1. RDS snapshot. This is the rollback point for the whole import. `--purge-feature` is for rehearsals.
2. Maintenance on, cron off, `noemailever` on, `bizlms_production = 1`, arm the guard.
3. `migration_parity_check.php --compare=<source baseline>`: the legacy tables must be intact.
4. `import_bizlms.php --preflight --all --decisions=... --expect-decisions-hash=<rehearsed>`: no blockers.
5. `--all --apply --confirm=<fp> --decisions=... --expect-decisions-hash=... --report=...`.
6. `migration_parity_check.php --compare=...`: exit 0, or exit 2 with Nitin's written acceptance.
7. `admin/cli/checks.php` clean; purge caches; disarm; cron on; maintenance off.

Stage B runs the same slice first. Its timings (I-20) set the maintenance window.

## Consequences

- Imported history becomes visible only where a reader exists. Several readers are new work (learner
  classroom, program, path and evaluation history; review lists; evidence views). Each ships behind a
  flag. Until they ship, some history is imported but seen only by managers or auditors.
- The legacy tables stay in the database indefinitely, and stay in the parity baseline. Storage cost
  is accepted. Their privacy gap is recorded above.
- Some numbers will differ from what BizLMS showed: duplicates collapse under target unique keys,
  invalid rows are skipped, deleted users are filtered by readers. Each difference is in the report
  with a reason.
- Several features attribute tenant from the user's **current** `open_path`, because the source
  stores no tenant. HRMS moves since then place old rows under the new tenant.
- Admin actions in Sentientia can still destroy imported history (unenrol, delete) until each
  feature's "protect imported history" code fix ships. Those fixes ship with the importer.
- The maintenance window length is unknown until Stage B.
- Most features need reader or engine code fixes before their data is useful. They are listed per
  feature in the mapping doc and ship with the importer.
- Every future BizLMS-like import (Enterprise N) can reuse the framework. The feature importers are
  Airpay-specific.

## Alternatives rejected

| Alternative | Why rejected |
|---|---|
| Re-use `migrate_all.php` / `data_migration.php` | Skip-if-populated, silent column loss, whole-table loads, DDL inside the transaction (Context). |
| Keep BizLMS plugins running on 5.2 | Contradicts the replacement decision; the code is not in the 5.2 package. |
| Always assign new ids | Certificates, `{event}` rows, enrol `customint1`, rating and request rows keep BizLMS ids and are not rewritten; new ids would point them at the wrong object. |
| Always keep ids | Seeds occupy low ids (skills), two sources share one target (institutes and rooms), fold targets are built from many rows. |
| Per-feature `legacy_*` columns or archive tables as idempotence keys (the twelve maps) | Twelve designs, twelve schema additions, install/upgrade drift risk. One map does it once. |
| Full-row JSON archive (`sourcedata`, `priordata`) in the map (Design A) | Copies every legacy row's personal data into a second table that then needs its own provider; roughly doubles storage; the legacy tables already are the archive. |
| Per-row `rowhash` (Design A) | Per-step fingerprints (count, max id, CRC) detect source change at run start and on resume at far lower cost; parity catches mutation after completion. |
| `--allow-remap` on collision (Design A) | Splits the id space; a collision is an operator problem. |
| `COUNT(*)` snapshots for side effects (Design A) | Slow on a large logstore. `MAX(id)` plus the existing checksums is cheaper and covers updates. |
| `isprimary` plus `targettable` in the map key (Design B) | The database could not enforce one primary row per source row. The sub-key model can. |
| `--incremental` (Design B) | The source is frozen after Phase 0; a changed source is a blocker to investigate, not to import. |
| One transaction per feature, always | Long undo logs and lost work on a crash at 95% for large features. Kept as an option for small ones. |
| A feature flag as the import gate | See "Gating". |
| Gate the reader fallbacks on the completion marker (both drafts) | Keeps code with known defects for no user. Removing the fallbacks with the importer is simpler. |
| Import into core tables (`course_completions`, `quiz_attempts`, `logstore`) | Fires observers (certificates, gamification, recompletion, webhooks) and changes live KPIs. |

## Open decisions for Nitin

Framework-level (feature-level choices are in the mapping doc):

1. Accept this ADR: the shared map, legacy tables as the archive, the id rule, a CLI guard instead of a
   feature flag.
2. Reader flags: default OFF as CLAUDE.md requires, but BizLMS showed this history to users today.
   Flip them for the Airpay customer at cutover, or after visual evidence is reviewed?
3. Who owns and signs `docs/cutover/bizlms-import-decisions.json`, and is a change after the rehearsal
   a re-approval event?
4. Rollback at cutover: RDS snapshot only, or is `--purge-feature` also allowed there ([CONFIRM] delete)?
5. The legacy-table privacy deliverable after sign-off: export and anonymise, or drop after an audit
   period (a separate ADR either way)?
6. Maps are still missing for certificates (`tool_certificate_issues`), `local_challenge`,
   `local_certification`, core `{event}` rows, non-course tag areas and the orphaned BizLMS enrol
   instances. They must exist before cutover; until then parity exits 2.
7. If Stage B shows the window is too short: approve an "inline key" mode for unreferenced high-volume
   leaf tables (recompletion SCORM tracks, email logs) that records only non-imported outcomes in the
   map? It is off by default and needs an amendment to this ADR.

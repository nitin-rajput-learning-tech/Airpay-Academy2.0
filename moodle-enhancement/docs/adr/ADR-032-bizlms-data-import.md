# ADR-032 — Import BizLMS feature data through one shared framework

**Status:** Proposed (2026-09-29), import decided by Nitin 2026-09-29; owner choices signed 2026-09-30, and the rest decided under Nitin's delegation of 2026-10-07 (section "Owner decisions, 2026-10-07 (delegated)"). Edits made because of those decisions carry a `2026-10-07 decision <id>` marker; `F-<nn>` is a follow-up item in the annex of `docs/cutover/OWNER-DECISIONS-2026-10-07.md`
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
    copies_files.php                               marker interface for importers that copy files (2026-10-07 decision IDN-04)
    sideeffect_guard.php, fingerprint.php          tripwire and source fingerprints
    legacymap_view.php                             the four map reads a step may make (resolve, resolve_many, entry, entries)
    decisions.php                                  the decisions file (shape in "Owner decisions")
    guard.php, guard_permit.php                    CLI guard, and the permit the runner demands before it writes
    capability_repair.php                          review and repair of role grants (section "Capabilities")
    report.php, parity.php, unclaimed.php
  classes/check/bizlms_import.php                  core status check
  classes/phpunit/legacy_schema_fixture.php        test trait
  classes/phpunit/importer_contract.php            test trait
  cli/import_bizlms.php                            new
  cli/repair_bizlms_capabilities.php               new (section "Capabilities")
  cli/migration_parity_check.php                   extended (both copies)
  lib.php                                          new in the ME tree: local_sentientia_platform_status_checks()
  db/install.xml + db/upgrade.php + version.php    three framework tables
<each target plugin>/                              (both trees)
  db/bizlms_import.php
  classes/bizlms/*.php
  tests/bizlms_import_test.php
  tests/fixtures/bizlms/<bizlms plugin>.install.xml   (one per source plugin; a target plugin may ship several,
                                                       2026-10-07 decision F-81)
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
| detail | char(255) NULL | codes only: up to four lower-case words joined by colons (`user_not_found`, `truncated:title`). Never an id, a name, an e-mail address or free text; `outcome::skip()` refuses anything else. The row already names its source table and id, and an id such as `orphan_user:123` is personal data in a table that must hold none. |
| runid | int(10) NN | |
| timecreated | int(10) NN | |

Keys: `UNIQUE uk_src (sourcetable, sourceid, subkey)` (128 characters plus an int, under the 333-character
index trap recorded after the 2026-06 gap build); `INDEX (targettable, targetid)` for reverse lookup and
the provenance guard; `INDEX (feature, outcome, reason)`; `INDEX (runid)`.

The key deliberately leaves out `targettable`. So one source row has exactly one primary map row, and
the database enforces it. Accounting is exact: source rows = primary map rows.

**`local_sentientia_legacyrun`**: id, runmode (`apply`|`purge`; named `runmode`, not `mode`, for readability next to the step
`mode`; it is not a reserved-word workaround, `mode` is reserved on none of the supported engines), status (`running`|`complete`|`failed`|`aborted`),
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
   PRESERVE target. Never inside a transaction. 2026-10-07 decision EV-26 (a correction of this rule): the floor is
   `max(target MAX(id), legacy MAX(id), legacy AUTO_INCREMENT - 1) + 1`, so a new native row can never take an id that
   legacy references still point at, including the id of a BizLMS row that was hard-deleted (a hard-deleted row is not in
   the legacy table, so `MAX(id)` alone misses it, while `mysqldump` keeps the table's AUTO_INCREMENT and the restored
   copy still knows every id ever issued). The counter is read through `SHOW CREATE TABLE`, because MySQL 8 caches
   `information_schema.TABLES.AUTO_INCREMENT` (`information_schema_stats_expiry`, 24 h by default); a new
   `legacy_reader::next_id(table)` parses it on MySQL and MariaDB and reads `last_value` on PostgreSQL. If the counter cannot
   be read the floor falls back to the legacy `MAX(id)` and the run warns. `writer::reset_sequence()` takes the floor and
   `runner::finalise_feature()` passes it. The request importer's `itemid` 0 for a gone path, classroom or program (COMMS-R2)
   stays as defence in depth. Lands in the pre-Stage-B framework batch, at the latest before the first native row is
   created at cutover.
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
    /** @return array<string, string> target table => path column (a NORMALISED path), for generic tenant verify;
     *  an INT root column (email_log.tenant_id, request.costcenterid) is checked by the importer's own verify()
     *  (2026-10-07 decision F-62) */
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
    public readonly legacymap_view $map;        // four reads; in dry run, an in-memory overlay sits under it
    public readonly tenant_resolver $tenant;
    public readonly lookups $lookups;           // users, courses, orgs: bulk-loaded, read-only
    public readonly legacy_reader $legacy;      // bounded, read-only access to other legacy tables
    public readonly text $text;                 // fit(): explicit truncation that reports
    public function decision(string $key): mixed;   // blocker if unset and no default
    public function servertz(): \DateTimeZone;      // Moodle's effective server timezone
}

/** What a step sees of the map: reads only. The runner keeps the legacymap (remember, reset, forget, batches, preload). */
final class legacymap_view {
    public function resolve(string $sourcetable, int $sourceid, string $subkey = ''): ?int;
    /** @return array<int, int|null> chunked at 1000 ids per query */
    public function resolve_many(string $sourcetable, array $sourceids, string $subkey = ''): array;
    public function entry(string $sourcetable, int $sourceid, string $subkey = ''): ?array;
    public function entries(string $sourcetable, array $sourceids, string $subkey = ''): array;
}

final class legacymap {   // the runner's own; static feature_complete(string $feature): bool is public
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

**2026-10-07 framework notes.** (1) `runner::preflight_feature()` wraps `$importer->preflight()` in `try/finally` only, so
a `blocked` exception thrown inside an importer's preflight (for example `$ctx->decision()` on an unaccepted key) escapes
although the runner has already recorded that blocker: it gains `catch (blocked $e) { $pf->block($e->getMessage()); }` (or a
non-throwing `context::peek_decision()`), with a registry or runner test (F-10). (2) `tenant_resolver::resolve()` checks a
candidate against `local_sentientia_org`, the table the org feature is filling, so the TENANT_OWNER importer (org) must NOT call
`resolve()` for its own rows; a public `tenant_resolver::root_is_registered(int)` replaces the try/catch around
`tenant::assert_valid` copied in `org_source.php` and the emails `log_step.php` (F-11, low priority, before Stage B).

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
   The histogram groups by BYTES (`GROUP BY BINARY` on MySQL and MariaDB): under a `*_ci` collation
   `ACTIVE` and `active`, or a value with a trailing space, would fall into one group and pass.
7. **A MAP insert never targets a table a PRESERVE step of the same feature owns.** On MySQL and
   MariaDB an insert with an explicit id raises AUTO_INCREMENT, so `insert_record` would take the next
   batch's legacy id and the next `import_record` would fail on the duplicate key, after which
   preflight reports the occupant as a collision and the feature is stuck. The registry refuses a MAP
   step whose target is a PRESERVE target, and the writer refuses any MAP insert (a step's primary row
   or a sub-row) into one.
8. **Targets are the importer's own.** The registry refuses a target that is not defined by the
   importer component's own `db/install.xml` (or `classes/schema` `TABLES`), that is a legacy table
   (known, detected in the database, or claimed or declined by any importer) or that is a framework
   table. `core_writes()` accepts only `registry::CORE_WRITES_ALLOWED` (`course`, `enrol`,
   `role_assignments`, `tag_instance`, `user_enrolments`), each with the operations reviewed for it and the
   section that reviewed them: `course` and `tag_instance` are UPDATE only; `enrol`, `role_assignments` and
   `user_enrolments` are insert and update. The writer enforces the operation: an importer that declares
   `course` for the open_* backfill cannot raw-insert course rows (no context, no sections), and one that
   declares `tag_instance` for a remap cannot insert tag instances. A core table is never adopted, purged
   or updated as "the import's own row". History and configuration tables of core
   (`course_completions`, `grade_*`, `logstore_standard_log`, `role_capabilities`, messages,
   notifications) can never be declared. Adding a table or an operation is an amendment to this ADR.
9. **Where the code lives.** The registry refuses an importer or a step whose class is defined outside
   the plugin's `classes/bizlms/`, because the static scan reads that directory and nothing else.

## Reading and performance

- `get_recordset_sql()` on MySQL buffers the whole result (`MYSQLI_STORE_RESULT`,
  `TOP lib/dml/mysqli_native_moodle_database.php:1301-1302`). So the framework owns every source read
  and pages it by keyset: `WHERE id > :watermark ORDER BY id`, with a LIMIT. Steps never open
  recordsets. Every page selects `t.id` FIRST, explicitly: `get_records_sql()` keys its result by the first
  column, and a production-only table can list its columns in any order, so `SELECT t.*` alone could merge
  rows that share the value of some other first column before the reader ever saw them.
- **Grouped steps** (dedupe and fold units) run in two phases, because legacy tables cannot be given
  new indexes: (1) scan `id` plus the group columns in PK pages and build `group -> [ids]` in memory;
  (2) process groups ordered by their **minimum source id**, fetching each batch by `id IN (...)`.
  The resume watermark is that minimum id. Keyset paging on a string group key is banned.
  `--max-group-scan` caps phase 1.
- Users, courses, orgs and declared map pairs are preloaded into integer arrays. No per-row SELECT.
- The report records rows per second per step. Stage B timings set the cutover window.
- **Accepted read-only exceptions to 'steps read only through `$ctx`' (2026-10-07 decisions F-14 and F-51):**
  `assignment_step::transform()` (org_roles) reads `role_assignments`, `role` and `role_context_levels` through global `$DB`,
  so a dry run shows two 'imported' where an apply gives imported plus folded; `user_identity_index` (users) reads `{user}`;
  `user_step::has_started()` (learningplan) reads `course_completions`. All are read-only, identical in dry and apply runs and
  not flagged by the static scan; the framework contract is frozen, and a lookups employee-id index plus a read-only role and
  context lookup (or a dry-run overlay) retire them later. F-52: a core table claimed as a source is owned by one feature
  (exams and skills cannot both claim `course`; skills worked around it by grouping per legacy skill); a core table may be
  claimed read-only by several features for fingerprinting, with no ownership, implemented only if a later importer needs it.

## Transactions

- **Batch mode (default).** One delegated transaction wraps a batch's target writes, its map rows and
  the step's watermark and counters. A crash rolls back the batch; the watermark stays; `--resume`
  continues. The map's unique key blocks a double insert if a watermark ever lags.
- **Feature mode (graft from Design A).** When `atomic()` is true and the preflight source total is at
  or below `--atomic-threshold` (default 50 000 rows), the runner wraps the whole feature in one outer
  delegated transaction. A crash leaves nothing. Features expected to be small declare `atomic()`
  (list in the mapping doc, section 2). Above the threshold they run in batch mode and the operator is
  told.
- **The source is fingerprinted for the whole run when it starts (added 2026-10-08).** A new `--apply` run
  reads the fingerprint (row count, max id, CRC; the step's own filter) of the source of EVERY load step of
  every feature it will process, before the first feature writes, and stores each as a `legacystep` row with
  status `pending`, in the same transaction as the run row. A step's fingerprint was taken only when the step
  opened, so a step the crash never reached had no row on `--resume`, and a source changed while the run was
  down looked like the source the run began with. Now `open_step` compares the source as it is with the stored
  fingerprint FIRST, for a pending row as for a started or finished one, and refuses with
  `source_changed_since_the_run_started:<step>`: a change made at any time between run start and the moment
  the step opens (in the same run, or while it was down) is a blocker, not an import. A pending step then
  starts from watermark 0. Left out on purpose: a recompute step (no source), a step whose source table is
  missing (the not_applicable path; a pending row whose table is gone is a source change), a feature added only
  as a dependency that is complete already (it runs no step), and a dry run (writes nothing). The pending rows
  are written before any feature transaction, so feature mode never rolls them back: a rolled-back feature
  returns its rows to pending with the run-start fingerprint, and the failure marker `<feature>.__feature` is
  written when no step of the feature has left pending. A pending row is not a started feature (`--status`,
  the status check). The check happens when a step opens, so a resume that refuses has already run the steps
  before the changed one. Cost: one more fingerprint per step at run start; the CRC cap is the same.
- **Report lines follow the commit.** The per-row counters and the CSV of non-imported rows are held while
  a batch (and, in feature mode, the whole feature) is inside its transaction, and written after the
  commit. A rolled-back batch leaves no phantom line, and `--resume` never lists a row twice.
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
   and the tables core APIs write WITHOUT an event: `user_preferences`, `role_capabilities`, `context`,
   `grade_grades`, `grade_grades_history`, `groups_members`, `cohort_members`, plus the importer's own
   extra list. `files` is watched for EVERY importer that does not implement the `copies_files` marker
   (2026-10-07 decision IDN-04, with F-12 and F-50; this replaces the earlier sentence that left `files` unwatched and
   promised a `core_writes` entry and a purge rule 'decided with the org importer'). The `file_rehome` copies (the org logo,
   cohort descriptions, the learning-plan cover, the classroom logo and the program logo, all made in `finalise()`) are a
   reviewed side effect: copy-only (a target that exists is skipped), insert-only and idempotent, and the originals are never
   touched. An importer that makes them declares the exact `[source component, source area, target component, target area]`
   list through the marker (`copies_files::allowed_file_areas()`) instead of a `core_writes` entry; after `finalise` the runner
   checks that every new `{files}` row lies in a declared target area (an importer without the marker that writes a file trips
   the tripwire, and so does an implementer writing outside its areas) and the run report counts
   `files_copied:<component>/<area>=N`. `--purge-feature` leaves the copies, which is harmless: a re-run with PRESERVE ids finds the
   same item and copies nothing. Five importers implement the marker (org, cohort_scope, learningplan, classroom, programs); any
   future caller must too. Signed key `framework.file_rehome_copies`. Any change outside declared targets and core writes aborts the
   run before the next feature. `MAX(id)` is O(1) and catches inserts; updates to state tables are
   caught by the existing parity checksums at the end (`migration_parity_check.php:116-133`).
   **Events are not writes at the moment they fire.** The standard log is an observer with
   `'internal' => false` (`admin/tool/log/db/events.php`): it runs only after the outermost transaction
   commits, and its buffered writer flushes every 50 events or at shutdown, so `MAX(id)` does not move
   while an event fired by a core API waits in the buffer. Every snapshot therefore flushes the log
   manager first (`get_log_manager(true)`), and a feature that runs in one outer transaction is checked
   twice: inside it (a direct write rolls back with the feature) and again after the commit and before
   `finalise()` and the marker (an event side effect). The second violation fails the feature with no
   marker, as in batch mode; its rows are committed, so the RDS snapshot is the way back. A third look
   comes after `finalise()` and the sequence resets, before the marker, so their side effects are checked
   too, and `finalise()` runs inside the feature's try so its failure is recorded like any other.
   **A trip sticks.** The rows a tripped feature committed are all mapped and every step is done, so a
   plain re-apply would take a fresh `before` snapshot, find the tripwire clean and write the marker.
   The runner therefore records the trip after any rollback (`bizlms_tripped_<feature>` = run id,
   `legacymap::tripped_run()`), and preflight blocks that feature (`tripwire_tripped_earlier`) for a plain
   apply, `--resume` and a dry run. It runs again only after a restore of the snapshot (the production
   way back), `--purge-feature`, or, in a rehearsal, `--acknowledge-tripwire=<run>` naming the run that
   tripped (refused when `bizlms_production = 1`, in the guard and in the runner). The completed feature
   clears the record. `--status` shows `tripped=<run>` and the status check is critical for it.
   **The standard log store must be on.** With `logstore_standard` off, or only a database store on, an
   event leaves no row in any watched table and the tripwire would report clean while blind, so `--apply`
   is refused (gating item 5b). **Dry runs** take snapshots too, without the log flush (a dry run writes
   nothing), and report `dry_run_tripwire`; it is reported and not fatal, because an online site has other
   writers.
4. **Static scan.** A test tokenises every PHP file below `*/classes/bizlms/` of every plugin type
   (sub-directories included) and fails on: `message_send`, `email_to_user`, `->trigger(`,
   `role_assign(`, `role_unassign*(`, `enrol_user`, `unenrol_user`, `enrol_try_internal_enrol`,
   `delete_user(`, `groups_add_member(`, `completion_completion`, `completion_info`, `update_state(`,
   `mark_complete`, `cohort_add_member`, `core_tag_tag::`, `update_course(`, `calendar_event::create`,
   every `grade_*` function and class, `queue_adhoc_task`, `feature_flags::` (the import never flips a
   flag), `call_user_func*` (it hides a call from the scan), `get_recordset`, `get_records(` outside
   the framework, `reset_sequence`, `set_config`, `unset_config`, the cache purges and `cache_helper::`
   outside `finalise()`, any `$DB->` write method, and the managers the maps forbid
   (`session_manager::`, `waitlist_manager::`, `path_manager::`, `program_manager::`,
   `request_manager::`, `cart_manager::`, `invoicer::`, `notifier::`, `delivery_log::log`,
   `evaluation_manager::submit_response`, `recompletion_engine::`, `skills_manager::`,
   `rating_manager::submit_rating`), and also `message_post_message`, `send_message`,
   `send_message_to_conversation`, `set_user_preference`, `unset_user_preference`,
   `$DB->replace_all_text()` and `$DB->change_database_structure()` (raw DDL). In importer code (not the
   framework) it also fails on a call through a variable (`$f(...)`), `new $class`, `$class::method()`, a
   banned name passed as a callable string (`'role_assign'`, `[$DB, 'insert_record']`,
   `'manager::send_message'`), `execute()` on the value a `db()`/`database()` accessor returns, and the
   framework's own `runner`, `writer`, `guard`, `guard_permit`, `sideeffect_guard`, `registry` and
   `capability_repair`. This is a deny-list and stays a tripwire for honest mistakes: only an allow-list
   of the namespaces importer code may call would be sound (an open follow-up), and core writes that fire
   no event (`file_storage`, preferences) are what the runtime tripwire's extra tables are for.
   A `$DB` write method that only the database object has (`insert_record`, `update_record`,
   `delete_records`, `set_field`, `delete_records_subquery` and the like) is a finding on ANY receiver;
   `execute()` counts when the receiver is `$DB`, an alias of it, `->db`, `$GLOBALS['DB']`, a
   `moodle_database` parameter, or when the method name is dynamic. `?->` is treated like `->`.
5. **Environment:** `$CFG->noemailever`, the task runner off, and tests that wrap every import in
   `redirectEvents()`, `redirectMessages()` and `redirectEmails()` and assert all three are empty.
6. **Permit.** The runner refuses to write or delete without a `guard_permit`, which only `guard`
   issues: `guard::permit_apply()` and `guard::permit_purge()`, which work the refusals out themselves (a
   caller cannot pass an empty list), and `guard::test_permit()` under PHPUnit. The guard conditions are
   therefore not a courtesy of one CLI script. The permit is a seam, not a lock; the static scan keeps
   importer code away from the guard, the runner and the writer.

## CLI

```
php local/sentientia_platform/cli/import_bizlms.php
    --status                         fingerprint, guard state, per-feature state, heartbeats
    --list                           features, owners, dependencies, source presence, unclaimed tables
    --preflight                      read-only, selected features; prints enum histograms and blockers
    --feature=a[,b] | --all          dependencies are added and sorted automatically
    (default) dry run                writes nothing to the database, not even bookkeeping
    --apply                          needs every guard below
    --resume                         continue the NEWEST apply run from its watermarks, if it did not complete
    --retry-skipped=<reason>[,..]    re-process rows skipped with a retryable reason (single-row steps;
                                     a grouped or derived step with such rows is refused, not skipped)
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
  applied reports those rows as `deferred`, not as failures. `deferred` exists **only in dry runs**: an
  apply run refuses an outcome with that reason (`deferred_outcome_in_apply`), because in an apply run
  every dependency has run and a deferred row would be stored as skipped for good.
- **Undeclared dependencies fail loudly.** A feature that resolves an id of, or reads the state of, a
  table another feature owns must list that feature in `depends()`, directly or through others. The map
  view the steps get, `is_deferred()` and `preload()` check it and throw `undeclared_dependency`
  (exit 1) otherwise; the alphabetical tie-break of the feature order would otherwise run the reader
  first.
- **Accounting.** Beyond `source rows (with the step filter) = primary map rows`, the unfiltered count of
  each claimed table must equal its primary map rows (`unmapped_rows`, in verify and in parity): a
  `source_filter()` may not make rows disappear, ADR id strategy 1 says every source row gets exactly
  one primary map row. A row a step does not want is archived with a reason.
- **Tenant values** in `tenant_columns()` must BE a normalised path, not merely normalise to one:
  `//1//5` and ` /1/5` fail verify and parity (the path-boundary defect class).
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
3. CLI maintenance mode is on (`climaintenance.html`, `admin/cli/maintenance.php --enable`; web
   maintenance mode, `$CFG->maintenance_enabled`, does not count: it lets administrators log in and edit
   the tables being written), unless `--allow-online` is given. `--allow-online` and `--purge-feature`
   are refused when `local_sentientia_platform/bizlms_production = 1`, which the cutover runbook sets.
4. `$CFG->noemailever` is true.
5. The scheduled-task runner is off (`cron_enabled` = 0; confirm the setting name on 5.2) and no task
   lock is held. The tripwire also detects a leak.
5b. `logstore_standard` is enabled (`tool_log/enabled_stores`): the event tripwire reads its table.
    `--acknowledge-tripwire` is refused when `bizlms_production = 1`.
6. The lock `bizlms_import` is taken through the core lock API. With the file lock factory it goes when
   the process dies. With `$CFG->lock_factory = db_record_lock_factory` a run killed with SIGKILL keeps
   it for `LOCK_LIFETIME` (12 hours); the refusal then quotes the newest running run's heartbeat age, and
   the operator releases the row only after confirming no import process is alive. Heartbeats in
   `legacystep` show a crashed run.
7. Every target plugin is at or above `requires_version()`.
8. Every decision the selected features need is present in `--decisions` with status `accepted` (a
   declared key carried as `finance-confirm` blocks that feature; see "Owner decisions"; since 2026-10-07 no key in the signed file has that status); at cutover,
   its hash equals `--expect-decisions-hash`.
9. Preflight found no blocker.

**Reader surfaces.** Each feature's new or changed reader surface (learner history pages, evidence
views, review lists, detail views) ships behind a default-OFF flag registered in its plugin's
`db/feature_flags.php`. The import never flips a flag. Whether the reader flags are ON for the Airpay
customer at cutover is Nitin's decision (BizLMS showed most of this history to users today).
2026-10-07 decision XC-IMPORTED-HISTORY-READERS records the rule: imported ENTITIES that other rows reference (orgs, roles,
courses and enrolments, plans, programs, classrooms, evaluation forms and their responses, rules) show on the admin pages
that manage them; imported HISTORY and LOG rows that nothing references (orders, ledger, credits, e-mail log, requests,
recompletion resets, HRMS sync runs, login days, transcripts) show only when the feature's imported-history flag is ON, which
Nitin flips after reviewing the visual evidence. The HRMS sync-run pages were the only history reader without a flag; they get
`sentientia.users.imported_sync_history`. Evaluation's imported forms and responses are the recorded exception: other imported
rows reference them (`local_classroom.trainingfeedbackid`, program feedback ids), and their admin pages match BizLMS role 9
visibility (EV-06), so hiding them would leave dangling references. Signed key `framework.imported_rows_on_admin_pages`.

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
     (the tag remap), a derived unit (`#tag_instance.id`) plus the importer's own `verify()` prove the accounting, and the
     filter afterwards matches exactly the folded and skipped rows, not 0 (2026-10-07 decision CRS-08);
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
   side effects on users, enrolments, completions, attempts, badges and grades. 2026-10-07 decision F-34 (amends this
   hook): the enrolments importer is the one DESIGNED exception, so 'no side effects on enrolments' is not literally true. It
   adds about 7,733 `user_enrolments` rows (April) and changes `enrol.status` (CRS-01), so `migration_parity_check.php` counts
   and checksums them (:64, :123-124) and runbook step 5 demands 100% PARITY. The compare must EXPLAIN the `user_enrolments`
   and `enrol` deltas through `legacymap` rows (feature `enrolments`, target table `user_enrolments` or `enrol`, outcome
   `imported`) and the `enrolmove` ledger, and report any unexplained delta as drift; step 5 of
   `MIGRATION-REHEARSAL-RUNBOOK.md` is amended to match. Until that is built the rehearsal parity gate fails by design.
5. **A skipped CRC is never a pass.** A legacy table whose CRC was skipped (no CRC32 on the engine, or
   more rows than `--crc-max-rows`) matched on count, max id and columns only, and an UPDATE changes
   none of those. `parity::comparison_problems()` turns it into an unproven item (exit 2), like a
   table that is not in the baseline. The baseline is taken with no CRC cap (`legacy_fingerprints()`
   default), or every comparison against it is unproven.

## Capabilities

The retired `migrate_all.php` copied every `role_capabilities` row of ten BizLMS capabilities to the
Sentientia capability that replaced it (`local/costcenter:manage_multiorganizations`, `:view`, `:manage`,
`:manage_ownorganization`, `:manage_owndepartments`; `local/courses:manage`, `:enrol`;
`local/classroom:manageclassroom`; `local/users:edit`, `:bulkstatuschange`), keeping role, context,
permission and modifier, skipping a row that already existed, and never revoking. It is not part of the
import and **must not be replayed** on a restored production database:

- In the production snapshot every capability of the ten except `local/classroom:manageclassroom` has a
  manager archetype, so the grants sit on the manager-archetype roles: core manager and the BizLMS
  tenant-admin role 9. Six of the Sentientia targets also have a manager archetype and the plugin install
  grants them to those roles itself.
- Four targets have none on purpose (`sentientia_org:manage_multiorganizations`, `:manage`,
  `:manage_ownorganization`, `:manage_owndepartments`). ADR-031 reserves creating, editing and deleting
  organisations and the multi-organisation scope for site administrators and holders of
  `local/sentientia_platform:crosstenant`. Replaying the BizLMS grants would give the tenant-admin role
  cross-tenant organisation delete, edit and visibility through `admin.php`, `delete_org`,
  `toggle_visibility` and `edit_org`.
- `local/classroom:manageclassroom` has no archetype in BizLMS, so on production it exists only as
  explicit overrides, and nobody knows which roles hold them (the local copy has none: BizLMS was
  uninstalled there). If trainers or a category-level org role held it, those users lose classroom
  management when the BizLMS code goes, because `sentientia_classroom:manage` reaches only the manager
  archetype. The same goes for any non-default override: PREVENT or PROHIBIT on role 9, or a grant at a
  category context.

**Replacement (built).** `cli/repair_bizlms_capabilities.php`, class `bizlms\capability_repair`. It is not a
feature importer: `role_capabilities` is permission configuration, not BizLMS history, and the tripwire
does not watch it.

1. Inventory (default, writes nothing): every `role_capabilities` row on a capability of a plugin that is
   missing from disk, per role and context, next to the Sentientia equivalent and whether the role already
   holds it, and with which permission.
2. `--allowlist=FILE` (JSON, signed as a whole: `approved_by`, `approved_on`, sha256 printed) holds the
   owner's two kinds of decision, and every inventory row must end up in one of them or the run does not
   finish clean:
   - `grants`: one line per grant to carry (`{role, context, legacy, target, permission}`). It is checked
     against the inventory: the role must really hold that legacy grant with that permission there, the
     target must be the equivalent of the legacy capability and be installed. Anything else is refused.
   - `declined`: what the owner reviewed and does not carry, each with a `reason`. Either one role grant
     (`{role, context, legacy, reason}`) or a whole missing plugin (`{legacy_component, reason}`). A plugin
     decline covers only the grants on capabilities that have **no** Sentientia equivalent. It never covers
     one of the ten in the map: that is where a real override hides (`local/classroom:manageclassroom` on
     trainers), so each of those is granted, already held (an archetype default at system context),
     withheld (item 4) or declined by name for that role and context. A line that is both granted and
     declined is refused, and both lines are named. A decline that matches no row is reported as a note
     and changes nothing (a typo leaves the row open, so it shows).
3. `--apply --confirm=<fingerprint>` (maintenance on unless `--allow-online`, refused in production) makes
   exactly the approved grants with `assign_capability()`: it never overwrites a row that exists and never
   revokes. In 5.1 `assign_capability()` clears the role cache and fires `capability_assigned`; it does
   not mark a context dirty.
4. It **never grants** `local/sentientia_org:manage`, `local/sentientia_org:manage_multiorganizations` or
   `local/sentientia_platform:crosstenant`, whatever the allow-list says. The cross-tenant capability goes
   by hand to the platform role Nitin names.
5. Exit 0 when every grant is decided (granted, already held with the same permission, withheld by item 4,
   or declined); 1 when an allow-list line was refused; 2 when grants remain that nobody approved or
   declined; 3 when a guard refused. The exit code is `capability_repair::exit_code()`, tested on both
   outcomes. "Already held" compares the permission. A role that holds the equivalent with a different
   one than BizLMS gave it (a legacy PROHIBIT at system context that the install's manager archetype
   turned into an ALLOW) is **divergent**: the repair never overwrites a row, so it cannot carry the
   restriction and the grant stays open (exit 2) until a decline names that role grant. An approved
   grant against such a target is refused (`target_held_with_a_different_permission`, exit 1).

**The declines are the answer for the archetype defaults.** BizLMS gives the manager archetype the grants
of all 22 plugins, so the inventory on a restored database always lists them on roles 1 and 9 (the rows
stay when the code goes). They are not "nothing to do": each is a reviewed decision, and the allow-list
records it. `docs/cutover/bizlms-capability-allowlist.json` is **signed** (Nitin Rajput, 2026-09-30, from the
Stage B inventory of the April 2026 production dump; see `docs/cutover/STAGE-B-CAPABILITY-INVENTORY-2026-09-30.md`).
It declines the 33 missing plugins by component (the 22 BizLMS `local_*` plugins, eight BizLMS blocks and the
three BizLMS enrol methods) and, for roles `manager` and `administrator` at system context,
`local/costcenter:manage_ownorganization` and `:manage_owndepartments` by name, because their
Sentientia equivalents have no archetype on purpose (ADR-031). Without the declines the archetype-default
inventory of roles 1 and 9 exits 2 (a test).

**What Nitin decided (2026-09-30, "do everything as recommended").** The inventory of the April 2026 dump
(520 role grants, all at system context) showed only two things beyond the archetype defaults, and both are
decided in the signed file, which records them under `resolved_decisions` (`open_decisions` is empty):
- `local/classroom:manageclassroom` is held by `administrator` (the Sentientia manager archetype already
  carries the equivalent to it) and by `trainer`, the only non-archetype holder. **One grant:** `trainer`,
  system context, `local/sentientia_classroom:manage`, ALLOW. BizLMS trainers manage classrooms on
  production today, so dropping it would break current behaviour; their attendance stays limited to their
  own sessions because the attendance rule keys on `local/sentientia_classroom:update` (merged 2026-09-30).
- The only mapped capability with a PREVENT or PROHIBIT, or at a context other than system, is the trainer
  PREVENT on `local/users:edit`. It is declined by name: trainers hold no `local/sentientia_users:edit`, so
  the PREVENT has nothing to narrow.

The file is tied to that dump. The Stage B rehearsal runs the inventory again on the real live backup and the
file is re-signed if the result differs. The signed file is checked in with its sha256 in the run log, and
`tools/check-bizlms-fixture-copies.php` keeps the copy the tests read equal to it.

**Same release as the org importer.** `local_sentientia_org\accesslib::legacy_cap()` still honours the
BizLMS grants in `can_manage_multi`, `can_manage`, `is_org_head`, `is_dept_head` and `can_manage_classroom`,
which `theme/sentientia`'s `core_renderer` uses. Because the BizLMS plugins are not uninstalled, their
`capabilities` rows stay in the restored database, so on it role 9 passes `can_manage_multi()` through
`local/costcenter:manage_multiorganizations`: an ADR-031 hole in the navigation. The fallbacks are removed
in the same release as the org importer, the way the reader fallbacks are, and not before, because
today they are what keeps tenant admins working on a restored UAT database.

## Privacy

- The framework tables hold no personal data. `detail` holds codes only (no ids). Reports hold ids
  and codes only.
- Every new target column that names a person is declared in its plugin's privacy provider, with en
  and hi strings. `USER_COLUMNS` (`privacy_coverage_test.php:52-63`) gains every actor-column name the
  mapping doc introduces (`enrolledby`, `markedby`, `initiatedby`, `sender_userid`, `subject_userid`),
  so the structural guard sees them. A plugin whose provider then fails (for example
  `sentientia_users`, a null provider today) fixes its provider in the same change.
  2026-10-07 decision F-86 (consolidates F-15, F-38, F-48 and F-73): `usercreated`, `usermodified`, `modified_by` and `trainerid` join `USER_COLUMNS` in ONE change after
  the program merge, in both trees, with the provider declarations the guard then flags (emails, talent, `course_type`,
  `course_category`, `email_overrides`, `email_rules`, `learningpath`, `learningpath_courses`, `cohort_scope`, `talent_path`,
  `talent_succ`, `talent_opp`, the users sync tables, classroom, programs; actor columns anonymised to 0 on erasure), and the
  program branch's temporary `COMPONENT_USER_COLUMNS` is deleted. Four clusters asked for overlapping additions with conflicting
  timing; this settles it. It does not block Stage B, because no import output depends on it.
- Secrets are never copied. The plaintext passwords in BizLMS welcome emails are the known case: those
  bodies and subjects are not imported (notifications section of the mapping doc).
- **Known gap, not solved here:** once BizLMS code is off disk, the legacy tables hold learner data
  that no provider exports or erases. The import never alters them. A separate, Nitin-gated deliverable
  after sign-off adds a platform provider section that exports legacy rows by user and anonymises them
  under the DPDP design. Declaring them without erasure would repeat the null-provider defect class.
  2026-10-07 decisions EV-19 and F-04: the separate ADR's scope also covers the import's own map (the evaluation 'assign'
  sub-rows, where an anonymous response and its assignment share one completion id) and the completion-ordered ids of implied
  assignments, and it must be accepted before any legacy evaluation table is anonymised or dropped; it is not a precondition
  for cutover. It also records how a non-zero cart credit balance is kept when its holder asks for erasure (Finance's call).
- **DPDP flow (2026-10-07 decision F-88, before cutover):** `privacy_manager.php:231-235` calls `delete_data_for_user()` for every
  `local_sentientia_*` provider that lacks `anonymise_data_for_user()`; only 8 providers implement it (classroom, learningpath,
  recompletion, org, xapi, programs, proctoring, compliance_report), so cart, evaluation, users, emails, request, ratings and skills
  delete or redact on a DPDP request. For each provider that holds imported history, write down whether delete is intended (login
  days: yes, IDN-06; cart balances and native invoices: Finance), and where the data is learning or compliance evidence add
  `anonymise_data_for_user()` following LRN-03's rule (keep the structured record, clear identifying free text). Record the outcome in the
  DPDP design note.

## Test approach

1. **Fixture trait** `\local_sentientia_platform\phpunit\legacy_schema_fixture`:
   `create_legacy_tables(string $fixturexml, array $only = [], array $extrafields = [])`,
   `truncate_legacy_tables()`, `drop_legacy_tables()`. It refuses unless `PHPUNIT_TEST` is set and
   `$CFG->prefix === $CFG->phpunit_prefix`. It loads a checked-in XMLDB copy and creates tables one at a
   time, dropping a leftover from a killed run first. Lifecycle (graft from Design A, for speed on the
   slow local MariaDB): create once in `setUpBeforeClass()`, truncate in `setUp()`, drop in
   `tearDownAfterClass()` inside `try/finally`. DDL is never run inside a transaction. On a real Moodle
   `testing_util::reset_database()` drops every table that is not in the install snapshot after each
   test that called `resetAfterTest()`, so "create once" really recreates the tables in every `setUp()`
   through the truncate path. That is correct and slow on the local MariaDB; budget for it.
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
   with `--apply`; a source change between runs makes resume refuse, whether the changed source belongs to
   a step the crash had reached or to one it never reached (run-start fingerprints, pending rows); a test-only logstore write trips
   the tripwire; the writer rejects an unknown field, an overlong char and a missing timestamp;
   interleaved groups are processed deterministically; the marker is set only after verify; the static
   scan; the one-owner rule; an unknown enum value blocks.
4. **Contract trait** `importer_contract` (graft from Design A), used by every feature test with that
   feature's seed: not applicable without tables; dry run writes nothing; apply reconciles; second
   apply is a no-op; resume after an injected failure; source change detected; collision blocks and
   header row is adopted; preserved ids; an importer with a tenant column lists `org` in `depends()`;
   no side effects (sinks and tripwire); privacy export and erase
   for every new user column.
5. **Feature tests** follow the fixture section of each feature in the mapping doc, including reader
   checks as a tenant admin in `@group tenant_isolation` (ADR-031 decision 8). All import tests are
   `@group bizlms_import` and run from the moodle5 dirroot, not `public/`.
   2026-10-07 decision F-30 (process rule): before merging ANY importer, run its preflight read-only against `bizlms_april`
   and cross-check every `source_spec` enum against a real value histogram (counts only). The evaluation build showed why:
   fixture tests could not catch `anonymous_response = 2`, because the checked-in install.xml documents the column as 0/1.
6. MySQL 8 and MariaDB are the gating engines; production is MySQL 8.0.44 on RDS (CLAUDE.md §2).
   **Not yet run:** the CRC32 SQL, `insert_records`, `import_record`, `reset_sequence`, the lock factory
   and the fixture lifecycle have only run in the SQLite stand-in used while the framework was written.
   `--group bizlms_import` must pass on MySQL 8 and on MariaDB 10.11 before Stage B (gating item for the
   first feature importer). The event-tripwire test skips itself when the environment cannot show what it
   tests (standard log store not writing). The owner-signed files (`docs/cutover/bizlms-import-decisions.json`,
   `bizlms-capability-allowlist.json`) are tested through byte-identical copies under
   `tests/fixtures/bizlms/`, because `docs/` is not deployed with the plugin;
   `tools/check-bizlms-fixture-copies.php` (CI, `tree-drift-check`) fails when a copy differs.
   **Open:** the `importer_contract` trait checks that a privacy provider is declared; the privacy
   export and erase check for every new person column (approach 4) is not in the trait yet. It lands
   with the first feature importer that adds a person column.
   Import SQL must still be portable `$DB` API SQL (no `GROUP_CONCAT`, `FIND_IN_SET`, `REGEXP`,
   `INSERT IGNORE`, `ON DUPLICATE KEY`; CSVs are split in PHP). A PostgreSQL run is not a gate.

## Build and run order

**Build:** Phase 0 is the framework plus source freezing (below). The framework contract is frozen after
Phase 0; changing it needs an amendment to this ADR, and the change rule below applies (2026-10-07 decision F-83, which
replaces the bare word 'frozen' used in CRS-08). Then features, each one deliverable,
in dependency order: org; then org_roles, cohort_scope, course_lookups, course_tags, legacy_logs,
exams, users, notifications, recompletion, cart (parallel); skills; classroom and program; learningplan;
evaluation, request and ratings; then the gap maps.

**Phase 0 status (2026-09-30, after the framework review).** P0.1 to P0.3 and P0.5 to P0.6 (schema,
framework, CLI, tests, the copy scripts retired, the seed scripts guarded) are in. **Still open:**
- P0.4: `migration_parity_check.php` does not call `parity::` yet (`legacy_fingerprints()` and
  `compare_fingerprints()` for the baseline and `--compare`, `invariant_problems()` as the
  `bizlms_import` invariant, and `comparison_problems()` for the exit codes). Without it the archive
  proof in the cutover slice, steps 3 and 6, has no script behind it. 2026-10-07 decisions F-34 and F-61: when it is wired,
  `migration_parity_check.php` takes `--decisions=FILE` and `--expect-decisions-hash` and passes them to
  `parity::invariant_problems()` (`parity.php:137,318-320` builds the verify context with `decisions::none()` otherwise, and
  the notifications `verify()` reads two decisions, so it would report `verify_error`), and it explains the enrolments deltas
  (hook 4).
- The `qr_scan.php` freeze below. `qr_scan.php:43,62` (both trees) still reads and inserts
  `local_classroom_attendance`. Today every scan ends in the catch because the insert omits two NOT NULL
  columns, so the archive is not changed by it, but a fix that made the insert work would change the
  archive after the baseline. It is classroom code fix 1 (mapping doc), which needs the new session
  tables and `session_manager::mark_attendance()`, and a page change needs visual evidence
  (CLAUDE.md §5). Hard prerequisite for the classroom importer and for the Stage B baseline; not done in
  the review pass because it is a user-visible page.
- The capability review (section "Capabilities") replaces the capability copy of the retired script; it
  is built, and runs first in the cutover slice. Its allow-list is signed (2026-09-30) and exits 0 in check
  mode on the rehearsal copy of the April 2026 dump with one grant (trainer, classroom manage); it is
  re-checked against the real live backup at the Stage B rehearsal.

**Framework change rule (2026-10-07 decision F-83).** Before Stage B the framework changes only for a defect that blocks a
run, changes import output or protects data; refactors wait. One framework change in both platform trees before Stage B, with
one PHPUnit re-init: `runner::preflight_feature()` catches `blocked` (F-10); the `copies_files` marker and the `{files}` tripwire
(IDN-04); the sequence floor with the legacy AUTO_INCREMENT (EV-26); `migration_parity_check.php` wired to `parity::` with
`--decisions` and `--expect-decisions-hash`, explaining the `user_enrolments` and `enrol` deltas through the map (F-34, F-61).
After Stage B (a refactor, not a defect): the in-place step kind (CRS-08); the contract-trait gaps (`importer_contract`
must not drop an install-snapshot table, a `contract_clear_core_writes()` hook, several fixture XMLs, a shared org stub in the
platform test namespace, combinable `setUp`, clear by feature provenance, a reviewed `core_writes()` table as a step target, a
framework undo for core-writing rehearsals, `grade_*` as read-only table names, a has-rows applicability predicate, a column
list on `source_spec`, a sanctioned post-load seed step, a dry-run overlay test, exams counting each quiz once in
`rows_total`: F-37, F-49, F-79); a keyed lookup `legacy_reader::fetch_by()` (F-09); `group_by()` accepting an SQL expression
(F-18); a generic PRESERVE child-row preflight (an opt-in check per PRESERVE step with the child table and FK column, replacing
evaluation's hand-rolled `rows_left_at_legacy_form_ids`: F-28); the per-person-column privacy export and erase check in
`importer_contract` (F-09, F-18, F-49); `tenant_root_columns()` so the parity tenant invariant covers INT roots (F-62). Before
cutover: count-bounded `accepted_reasons` (`feature:code<=n`, so cutover exits 2 when a needs-owner count exceeds what was
accepted: F-27). Until that exists the cutover run report compares each accepted code's count with its Stage B count, and any
increase is a re-approval event.

**Stage B gates (each must close before an importer runs on the restored live copy).** They are all
recorded above; this is the one list:
1. `--group bizlms_import` passes on MySQL 8.4 and on MariaDB 10.11. None of the tests written in the
   review rounds (registry rules, event tripwire, sticky tripwire, permit, capability repair, report
   rebuild, subkey shape, core-write operations) has been run yet.
2. The capability allow-list `docs/cutover/bizlms-capability-allowlist.json` is signed by Nitin (2026-09-30)
   with his decisions on the `local/classroom:manageclassroom` overrides and the mapped capabilities at a
   context other than system or with PREVENT or PROHIBIT, and `repair_bizlms_capabilities.php` exits 0 on
   the restored database. Closed on the rehearsal copy of the April 2026 dump (check mode, exit 0, one
   grant). **Still to do:** re-run the inventory on the real live backup at the Stage B rehearsal and
   re-sign the file if it differs.
3. `local_sentientia_org\accesslib::legacy_cap()` is removed in the same release as the org importer, and
   `local/sentientia_platform:crosstenant` is granted deliberately, by hand, to the platform role Nitin
   names. Until then role 9 passes `can_manage_multi()` on a restored database through
   `local/costcenter:manage_multiorganizations`. 2026-10-07 decision IDN-05: the code half is done (the org checks no longer call
   `legacy_cap()`, `local_sentientia_org/classes/accesslib.php:301-325`; the only remaining caller is the trainer dashboard check
   in `theme/sentientia`) and the capability repair runs first in the cutover slice. The platform role is created by
   `tools/uat/adr031_crosstenant_role.php --target=<wwwroot> --config=<cfg> --dry-run`, then `--apply` (migration plan step
   4f-f), at Stage B and at cutover, with the one capability `crosstenant` and NO members; members are added by hand at
   `/admin/roles/assign.php?contextid=1` only when Nitin names them (`org.crosstenant_platform_role`). Site admins stay the only
   cross-tenant callers until then.
4. P0.4: `migration_parity_check.php` calls `parity::comparison_problems()`, with the baseline taken with
   no CRC cap, takes `--decisions` and `--expect-decisions-hash`, and explains the enrolments deltas (parity hook 4; 2026-10-07
   decision F-34).
5. `local/sentientia_pages/qr_scan.php` stops reading and inserting `local_classroom_attendance`
   (classroom code fix 1, with visual evidence).
6. The standard log store is enabled on the database the run is on (gating item 5b).
7. The registry loads all 19 importers (2026-10-07 decisions EV-25, F-33, F-43, F-44, F-78). Until classroom and program were on the
   integration branch, `registry::load()` threw `unknown_dependency` for ratings, evaluation and request and no feature could
   load. Committed on `claude/gap-integration`: classroom (b316c138a), the runner's dry-run FOLD fix (6991ac1b6) and program
   (bae085600). What remains is to confirm `registry::load()` returns all 19 after the PHPUnit re-init, then run the dry runs of
   evaluation, request and ratings (`deferred` parents are reported) and switch the program, learningplan, classroom and skills
   tests from their stub parents to the real org importer.
8. The signed decisions file carries every key an importer declares (2026-10-07 decision F-84): an importer that declares a key
   missing from the file blocks at preflight, so the file and both fixture copies change in ONE commit, before or together with
   any importer change that declares a new key (EV-16, `cart.finance_keys_status`, CRS-01/02/03), and before the Stage B
   rehearsal pins the hash. No `accepted_reasons` list is added then.

**Phase 0 source freezing:** move `SE local/sentientia_pages/qr_scan.php` and `qr_attendance.php` off
the legacy tables (they check and write `local_classroom_attendance`, migration plan :174-177); make
`migrate_all.php` and `data_migration.php` refuse and point to the new CLI; block
`SE local/sentientia_pages/cli/setup_costcenters.php`, `setup_bizlms_data.php` and
`fix_all_bizlms_data.php` on a database that holds legacy tables.

**Cutover slice** (after the two core hops):
0. `repair_bizlms_capabilities.php` (inventory, then the owner's allow-list; section "Capabilities"),
   before anything else reads a role. Dry by default.
1. RDS snapshot. This is the rollback point for the whole import. `--purge-feature` is for rehearsals.
2. Maintenance on, cron off, `noemailever` on, `bizlms_production = 1`, arm the guard.
3. `migration_parity_check.php --compare=<source baseline>`: the legacy tables must be intact.
4. `import_bizlms.php --preflight --all --decisions=... --expect-decisions-hash=<rehearsed>`: no blockers.
5. `--all --apply --confirm=<fp> --decisions=... --expect-decisions-hash=... --report=...`.
6. `migration_parity_check.php --compare=... --decisions=... --expect-decisions-hash=<rehearsed>`: exit 0, or exit 2 with
   Nitin's written acceptance. (Pass the decisions: every importer's `verify()` reads them, and without the file the
   `bizlms_import` invariant is SKIPPED, exit 2. Wired 2026-10-07, review fix round 1; `parity::compare_invariant()`.)
7. `admin/cli/checks.php` clean; purge caches; disarm; cron on; maintenance off.

Stage B runs the same slice first. Its timings (I-20) set the maintenance window.

After the Stage B rehearsal (2026-10-07 decisions IDN-02, CRS-04, CRS-07, CRS-10, COMMS-C1, `cart.accepted_reasons`, F-54): the lead
tabulates every needs-owner reason with its count per feature, Nitin accepts each in writing, and ONE batch edit adds the
`feature:code` strings to the top-level `accepted_reasons` list of the signed file (and both fixture copies), with the counts in
the approval note; the new hash is pinned and `--expect-decisions-hash` uses it. Nothing is accepted before that, because a
pre-acceptance would pass any count the live backup produces.

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
  feature's "protect imported history" code fix ships. Those fixes ship with the importer. 2026-10-07 decision LRN-10: an admin
  may unenrol an imported enrolment that carries no completion, progress or attendance, on an active learning path, classroom or
  program (BizLMS allowed it); every row that carries history stays blocked.
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
| Per-row `rowhash` (Design A) | Per-step fingerprints (count, max id, CRC), taken for every step when a new run starts and compared when each step opens (so also on resume), detect source change at far lower cost; parity catches mutation after completion. |
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
   period (a separate ADR either way)? 2026-10-07 decision EV-19: its scope includes the import map and the assignment-id order,
   and it must be accepted before any legacy evaluation table is anonymised or dropped; it is not a precondition for cutover.
6. Maps are still missing for certificates (`tool_certificate_issues`), `local_challenge`,
   `local_certification`, core `{event}` rows, non-course tag areas and the orphaned BizLMS enrol
   instances. They must exist before cutover; until then parity exits 2. 2026-10-07: the orphaned enrol instances (gap G6) are
   decided and built (convert to manual, CRS-01 to CRS-05); the rest stays open.
7. If Stage B shows the window is too short: approve an "inline key" mode for unreferenced high-volume
   leaf tables (recompletion SCORM tracks, email logs) that records only non-imported outcomes in the
   map? It is off by default and needs an amendment to this ADR.

## Owner decisions, 2026-09-30 (as recommended)

Nitin's instruction on 2026-09-30 was to do everything as recommended. Each line below is one owner
choice: question -> answer -> why. Where the ADR or the mapping doc proposed an answer, that answer is
recorded. Where they offered options with no recommendation, the answer was chosen by the owner rules and
the line ends with the rule in brackets: (rule 1) nothing is lost, (rule 2) nothing acts on its own,
(rule 3) nothing is more visible than on BizLMS today, (rule 4) privacy-preserving where history is not
lost, (rule 5) tenant-scoped when in doubt. Two recommended answers that differ from what a rule would
have chosen are marked in their lines: `request.pending` and `request.hidden_rows`.

`docs/cutover/bizlms-import-decisions.json` is the machine copy of this section. Its sha256 is pinned at the
Stage B rehearsal and must match at cutover (`--expect-decisions-hash`). Nitin signs it. Any change after the
rehearsal is a re-approval event. If the two ever disagree, the JSON is what runs.

The JSON now also holds the decisions of 2026-10-07 (section "Owner decisions, 2026-10-07 (delegated)" at the end of this
ADR): 138 decisions, a top-level `delegated_on` and `delegation_note` next to `approved_by` and `approved_on`, and still no
`accepted_reasons` list.

**File shape** (read by `bizlms\decisions::load()`; the first loader read it as a flat map and so found none of
these keys, which is the defect the framework review recorded):

```
{ "version": 1, "approved_by": "...", "approved_on": "YYYY-MM-DD", "basis": "...",
  "decisions": { "<feature>.<key>": {"value": ..., "why": "...", "source": "...", "status": "accepted"} },
  "accepted_reasons": ["<feature>:<code>", ...],            optional, absent today
  "enums": { "<legacy table>.<column>": {"<value>": "<meaning>"} } }   optional, absent today
```

- Only an entry whose `status` is `accepted` is a decision. `has()` and `get()` ignore any other, and a
  selected feature that declares such a key is blocked at preflight (`decision_not_accepted:<key>:<status>`);
  `context::decision()` throws the same. The importer's default never stands in for an open choice.
- `accepted_reasons` lists needs-owner reasons the owner accepts; parity exits 2 for any needs-owner reason
  not listed. `enums` maps values of a declared enum column so preflight stops blocking on them. Neither is
  filled in by an importer's build (2026-10-07 decisions IDN-02, EV-23 and F-27 correct the earlier wording): the OWNER adds a
  reason after the Stage B rehearsal, only for codes that occur, with its count, because an acceptance carries no count and a
  pre-acceptance would also pass any count the live backup produces. Count-bounded acceptance (`feature:code<=n`) is a
  before-cutover framework item; until then the cutover run report compares each accepted code's count with its Stage B count
  and any increase is a re-approval event. The signed file does not carry either section yet.
- The report lists the decisions used with their status, the ones not accepted, and who approved the file.

**FINANCE items (two), accepted under delegation on 2026-10-07 (decision cart.finance_keys_status, F-01).** The import is not
blocked, and nothing acts on these until Airpay Finance answers: `cart.credit_balances` (honour, pay out or write off, and who owns
the liability) and `cart.erpnext_invoices_legal` (are the ERPNext invoices the legal tax invoices). They were `finance-confirm`;
Nitin delegated them on 2026-10-07 and both are now status `accepted`, with `frozen_pending_finance` and
`reference_only_pending_finance` as recorded values, and the cart importer DECLARES them. **Airpay Finance was not consulted:**
`accepted` records the delegated recommendation, not a Finance sign-off, and each `why` in the file says so. What the import does
(frozen, admin-only history behind default-OFF flags; 'Issued in ERPNext as <number>', no link-out, no new invoice numbers) does
not depend on Finance's answer. April facts: 0 credit bookings, 0 ledger rows, 0 invoice rows, INR 0, ERPNext never configured
(the one Rs 10 paid sale has no invoice anywhere). If Stage B shows any non-zero balance or invoice row it goes to Finance before
cutover; a Finance answer changes the file and is a re-approval event. The open Finance questions (credits, ERPNext invoices,
the six native-tax-invoice points including retention against DPDP erasure) are in `OWNER-DECISIONS-2026-10-07.md`. The framework
mechanism stays: a declared key carried as `finance-confirm` still blocks that feature (covered by the toy sample file).

**Framework (ADR open decisions)**

- `framework.adr_accepted` Accept the ADR (shared map, legacy tables as the archive, id rule, CLI guard instead of a flag)? -> Yes -> it is the recommended design.
- `framework.reader_flags_default` Reader flags default? -> OFF -> CLAUDE.md flag rule.
- `framework.reader_flags_airpay_at_cutover` Flip reader flags ON for Airpay at cutover? -> Yes, but only after Nitin reviews the visual evidence, and the flip is his call -> BizLMS showed this history, but the flag rule stands.
- `framework.decisions_file_owner` Who owns and signs this file? -> Nitin -> he is the owner of record.
- `framework.decisions_change_after_rehearsal` Is a change after the rehearsal a re-approval event? -> Yes -> the rehearsed hash is what cutover must match.
- `framework.cutover_rollback` Rollback at cutover? -> RDS snapshot only -> one clean restore point, no partial deletes on production.
- `framework.purge_feature_on_production` May --purge-feature run at cutover? -> No -> it deletes rows, and production has no delete path.
- `framework.legacy_table_privacy` Legacy-table privacy after sign-off? -> A separate ADR later, not decided here -> the legacy tables stay untouched until then.
- `framework.inline_key_mode` Approve the 'inline key' mode for high-volume leaf tables? -> Not now -> off by default, revisit only if Stage B shows the window is too short.
- `framework.protect_imported_history` Admin delete of imported history? -> Blocked on imported rows -> nothing is lost (rule 1).

**Tenant attribution (all features)**

- `tenant.unresolved.<feature>` (13 features: cohort_scope, course_lookups, legacy_logs, exams, users, notifications, cart, skills, classroom, program, learningplan, evaluation, request) Rows whose tenant cannot be resolved? -> Import with no tenant path, visible to cross-tenant callers only, always reported -> never global (ADR-031).

**org**

- `org.unmapped_columns` Copy multipleorg, childpermission, shell? -> No, they stay in local_costcenter -> nothing reads them and nothing is lost.

**org_roles**

- `org_roles.value_filter` Which org-role permission rows count? -> Only value = 1 (tables expected empty) -> the document's proposal, confirmed at rehearsal.

**course_lookups**

- `course_lookups.featured_scope` Featured courses scope? -> Re-homed per tenant -> narrower than BizLMS's global list (ADR-031).
- `course_lookups.coursedetails_unhomed_columns` Add columns for enrolment dates, duration, prerequisites? -> No, leave them in the legacy table -> no reader needs them.
- `course_lookups.declined_config_tables` local_moduleconfig and local_filters? -> Stay in place; local_certificate goes to the certificates map -> configuration, not history.

**legacy_logs**

- `legacy_logs.retention` Retention of imported admin logs? -> Keep, no purge -> nothing is lost (rule 1).
- `legacy_logs.description_erasure` Erasure of first names in log descriptions? -> Keep the row, scrub the name -> privacy without losing history (rule 4).

**exams**

- `exams.multi_quiz` Multi-quiz exam course? -> One exam per quiz -> keeps every quiz's history.
- `exams.forum_pseudocourses` Forum pseudo-courses in the catalog? -> Hidden -> BizLMS did not list them (rule 3).

**users**

- `users.transcript_status_map` Transcript status normalisation list? -> Approved as written, raw value always kept -> the document's list.
- `users.admin_runs_tenant` HRMS runs uploaded by a site admin? -> Tenant 0, cross-tenant only -> the legacy costcenterid is not reliable.
- `users.uniquelogins` Import local_uniquelogins? -> Yes -> nothing is lost (rule 1).
- `users.transcript_counts_toward_totals` Do transcript rows count toward completion totals? -> No -> shown separately as imported history.
- `users.erasure_treatment` On erasure, anonymise or delete imported transcript and sync-error rows? -> Anonymise -> keeps history, removes the person (rules 1 and 4).
- `users.userdata_reconciliation` If local_userdata and user.open_path disagree? -> user.open_path wins, report only -> local_userdata is a derived mirror.
- `users.positions_domains_lookup_import` Import local_positions and local_domains (G4)? -> Yes, ids kept -> the profile otherwise shows bare ids.

**notifications**

- `notifications.import_bodies` Import email bodies? -> Yes, with credentials redacted -> history without secrets.
- `notifications.queue_status` Status for undelivered queue rows? -> not_sent, never sent -> nothing in the old queue is ever delivered.
- `notifications.keep_sender` Keep sender identity? -> Yes -> the column map imports it and the provider covers it.
- `notifications.retention` Retention of imported email rows? -> Keep, no purge -> nothing is lost (rule 1).
- `notifications.deleted_recipient_sent` Status-1 rows to a recipient already deleted when BizLMS ran the send? -> sent, with a note; a recipient deleted after the send was delivered to and imports as plain sent -> that is what BizLMS recorded (wording corrected 2026-10-07, COMMS-N6; April: 1 of 342 rows to now-deleted users).

**recompletion**

- `recompletion.rule_tenant` Rule tenant? -> Global (tenant 0) -> same as BizLMS.
- `recompletion.preview_attempts` Import teacher-preview attempts? -> No, they stay in the legacy table -> not learner history.
- `recompletion.enable_imported_rules` Enable imported rules at cutover? -> No, all disabled -> nothing resets learners on its own.
- `recompletion.rebuild_legacy_behaviours` Which legacy behaviours must be rebuilt? -> None at cutover -> all rules stay disabled (rule 2).
- `recompletion.learner_history_surface` Where do learners see past cycles? -> The recompletion history page and evidence view, flag OFF -> per the code fixes.
- `recompletion.archive_shape` Archive shape? -> One generic table with a JSON payload -> the document's proposal.
- `recompletion.engine_archive_before_delete` Should the engine archive before it deletes? -> Yes -> required before any rule is enabled.
- `recompletion.deploy_upstream_plugin` Deploy upstream local_recompletion on 5.2 instead? -> No, out of scope -> the ADR replaces BizLMS code, it does not run it.

**cart**

- `cart.synthesize_ledger` Synthesize missing ledger payment rows? -> No, import only what exists -> no invented money rows.
- `cart.order_tenant` Order tenant? -> The buyer's current root -> the same as native Sentientia orders.
- `cart.abandoned` Abandoned checkouts? -> Imported, admin-only -> nothing lost, not shown to learners.
- `cart.imported_visibility` Who sees imported money history? -> Admins only, frozen -> Nitin's money rule; the cart reader must hide legacy rows from owners.
- `cart.admin_refund_imported_orders` May admins refund imported orders? -> No, blocked -> no refund or payout from imported orders.
- **FINANCE-CONFIRM.** Honour, pay out or write off legacy credit balances, and who owns the liability? -> Import is not blocked; balances are frozen history and nothing acts on them until finance answers.
- **FINANCE-CONFIRM.** Are the ERPNext invoices the legal tax invoices (and link out)? -> Import is not blocked; shown as reference only, no link-out, until finance answers.
- `cart.cash_drawer_rows_without_order` Cash-drawer rows with no order (historyid 0)? -> Import, cross-tenant admins only -> nothing lost, nothing more visible (rules 1, 3, 5).
- `cart.stale_task_adhoc_rows` Delete stale BizLMS cart rows from task_adhoc? -> Not here; a separate [CONFIRM] if ever wanted -> nothing is deleted by this import (rule 1).

**skills**

- `skills.catalogue_scope` Skills catalogue scope? -> One shared catalogue -> the status quo the document proposes.
- `skills.merge_categories` Merge policy? -> Categories by exact name, skills never -> the document's proposal.
- `skills.seed_rows` Keep the 48-skill seed on production? -> Yes, never deleted here -> deleting is a separate [CONFIRM] (rule 1).
- `skills.level_proficiency` Level-to-proficiency map? -> Name heuristic (awareness 1, basic/beginner/foundation 2, intermediate 3, advanced 4, expert 5, else 1) -> the document's proposal; the concrete CSV was generated on 2026-10-07 from the 17 April levels (LRN-07, level 16 reviewed to 2) and is rechecked on the live backup before the hash is pinned.
- `skills.source_label` Source label on migrated skills? -> 'import' -> honest about where the row came from.
- `skills.history_from_archive` Grant skill history from the recompletion archive too? -> Yes -> nothing is lost (rule 1).
- `skills.skillmatrix` Import local_skillmatrix? -> No -> it had no writer and no live reader.
- `skills.interests` Interests: build a consumer or import for the record? -> Build a reader behind the existing flag, OFF -> per code fix 7.

**classroom**

- `classroom.status_new_hold` Add draft and on-hold classroom states? -> Yes (codes 5 and 6) -> collapsing would show unstarted classes as active.
- `classroom.waitlist_closed` Waiting rows on closed classrooms? -> removed -> nobody can be promoted into a closed class.
- `classroom.waitlist_open` Waiting rows on open classrooms? -> waiting only after the auto-promote guard ships, otherwise removed -> nothing promotes anyone on its own (rule 2).
- `classroom.pathless` Classroom with no usable path? -> Cross-tenant only -> never guess a tenant.
- `classroom.costs_as_columns` Classroom costs as columns? -> No, legacy table only -> no finance or audit reader.
- `classroom.qr_checkin_requires_roster` QR check-in requires the roster? -> Yes -> per code fix 1 (time window not decided).

**program**

- `program.completed_without_date` Completed but no completion date? -> Completed, flagged -> the certificate path treated it as completed.
- `program.inactive` Inactive programs? -> Archived, not Draft -> BizLMS had no draft state.
- `program.inactive_history_to_learners` Do learners see history in inactive programs? -> No, admins only -> BizLMS treated them as switched off (rule 3).
- `program.empty_levels` Empty auto-created levels? -> Skip -> a kept empty level would count as completed.
- `program.deleted_users` Enrolments of deleted users? -> Import for audit -> history is kept, readers filter.
- `program.pathless` Program with no usable path? -> The creator's root, else no path -> the document's proposal.
- `program.bk_tables` Non-empty _bk tables as a 'previous completion' history? -> No, archived in the legacy tables -> BizLMS never showed one (rules 1, 3).

**learningplan**

- `learningplan.not_completed` Learners who have not completed? -> In progress when a course is done, else Enrolled -> derived from real completions.
- `learningplan.enforce_rules` Enforce approval, self-enrol and sequence rules? -> Store only -> nothing enforces or acts on its own (rule 2).
- `learningplan.dates_as` Plan start and end dates? -> Sentientia's own dates (enrolment window) -> the document's proposal, to be checked at rehearsal.
- `learningplan.history_on_archived_paths` Completed history on archived paths? -> Admins only -> BizLMS hid these plans from learners (rule 3).
- `learningplan.tenant_fallback_order` Tenant fallback order for empty paths? -> Accepted as written (cost centre, enrolled users' shared root, creator's root, else none) -> the document's order.

**evaluation**

- `evaluation.open_forms` Still-open forms? -> Archived -> active would reopen answering with no assignment check.
- `evaluation.multichoicerated` Weighted multichoice questions? -> Plain multichoice -> weights stay in the legacy tables.
- `evaluation.sp_anonymous_subject` Anonymous supervisor forms? -> Hide the subject too -> the subject could identify the respondent.
- `evaluation.legacy_anonymous_linkage` Anonymise or drop the legacy user links of anonymous answers? -> Neither here; the separate legacy-table privacy ADR decides -> anonymous answers stay anonymous in Sentientia.
- `evaluation.trainer_feedback_form_names` Add the trainer's name to trainer feedback forms? -> No, keep the BizLMS name -> nothing more visible than BizLMS (rule 3).
- `evaluation.imported_forms_read_only` Make imported forms read-only? -> Yes -> protects the answers (rule 1).

**request**

- `request.pending` Legacy pending requests? -> Course and path stay actionable, a person still decides; classroom, program and certification are read-only -> the document's proposal (rule 2 exception).
- `request.pending_classroom_program` Pending classroom and program requests? -> History only -> deciding them would fail silently.
- `request.certification` Certification requests? -> Unmapped, history only -> no owner entity exists yet.
- `request.decided_route` Route label on decided legacy rows? -> admin -> no new value to maintain.
- `request.hidden_rows` Rows BizLMS hid? -> Show -> the document's proposal (rule 3 would have filtered them).
- `request.tenant_basis` Tenant of a request? -> The requester's current root -> the source has no tenant column.
- `request.pending_approver` Approver of legacy pending requests? -> Sentientia routing -> the document's proposal.
- `request.comments` Request comments? -> Folded into the decision note -> expected empty; preflight blocks if the table has rows, and a decision on an imported row appends to the note (2026-10-07, COMMS-R4).

**ratings**

- `ratings.invalid_rows` Invalid rating rows? -> Skip and report -> they stay in the legacy table.
- `ratings.blank_reviews` Blank reviews? -> Import, hidden -> nothing lost, nothing shown.
- `ratings.deleted_users` Deleted users' ratings? -> Keep -> averages stay what BizLMS showed.
- `ratings.show_dislike_counts` Show dislike counts? -> Yes, behind the reaction flag -> BizLMS showed them (rule 3).
- `ratings.certification_area` Ratings under the certification area? -> Skip and report until a target exists -> no reader asks for that area.

**gaps**

- `gaps.other_tag_areas` Tag instances of classroom, learning plan and evaluation (G5)? -> Counted and left in place -> no plugin is uninstalled.
- `gaps.classroom_program_skill_tags` Skill and level tags on classrooms and programs (G8)? -> Stay in the legacy tables -> no Sentientia reader needs them.

**Left unanswered on purpose, and what became of each (2026-10-07)**

- `accepted_reasons` (the wrong name `accept_needsowner.<feature>.<reason>` is replaced by the loader's real shape, a top-level list
  of `"feature:code"` strings): not pre-accepted, and still not (2026-10-07 decisions IDN-02, EV-23, COMMS-C1, F-82). Each
  acceptance covers an actual rehearsal outcome (a count of rows and a reason code), so it is added after Stage B, in writing, by
  Nitin, as one batch with the counts in the approval note. Until then parity exits 2, which step 6 of the cutover slice allows only
  with Nitin's written acceptance. Expected Stage B reasons from April are in the migration plan, section 11.
- `skills.level_proficiency.csv`: filled on 2026-10-07 (LRN-07); the skills feature is no longer blocked by it.
- Open decision 6 (maps for certificates, `local_challenge`, `local_certification`, core `{event}` rows, non-course tag areas; also
  gaps G7 and G9) is engineering work or a Stage B check, not an owner choice. The orphaned-enrol-instance choice (gap G6) is
  decided (last line below), so it no longer 'needs Nitin'.
- Facts to collect, not decisions: production row counts (I-20), `SHOW COLUMNS` for the production-only columns, server timezone
  (April: Asia/Kolkata), logstore retention, the `local_ratings/review_enable` setting (April: 0), whether `paygw_airpay` is deployed
  on 5.2 (yes: `moodle-enhancement/payment/gateway/airpay`, April config version 2024100700.1) and whether the PayPal gateway was
  used for any cart order (no: April `payment_gateways` has only airpay and the core `payments` table has 0 rows). Re-read all of
  them on the live backup at Stage B.
- Classroom QR check-in time window: decided by the fixes-0930 merge b59adb58c (30 minutes before the start to 30 minutes after the
  end). Which notification types have no Sentientia sender once BizLMS stops sending: COMMS-N7
  (`gaps.notification_sender_parity = build_flagged_off`, mapping doc section 21, G10).
- Orphaned BizLMS enrol instances (gap G6) -> convert each enrolment to a manual enrolment in the same course (status, start and end kept; original instance in the legacy map) -> the instances have no plugin code, so nobody can manage them (no unenrol, suspend or expiry handling) while core keeps granting access through any enabled instance (2026-10-07 decision XC-G6-WHY corrects the earlier reason, 'these learners would lose course access': core keeps granting access through an enabled instance whether or not its plugin is on disk). Each fully converted instance is then disabled when proven per (user, course) pair (CRS-01). Verified at the rehearsal.

## Owner decisions, 2026-10-07 (delegated)

On 2026-10-07 Nitin delegated the open owner decisions ("self review and decide recommended option"), on top of the signed basis
"do everything as recommended". A critic pass corrected three decisions and added two. All 84 decisions, where each is
implemented, and the questions that still need Nitin are in `docs/cutover/OWNER-DECISIONS-2026-10-07.md`; the machine copy is
`docs/cutover/bizlms-import-decisions.json` (36 keys added or corrected, `why` starts `[delegated 2026-10-07]`). No feature flag is
flipped and nothing is deleted. Airpay Finance was not consulted for the cart keys. Each line is key -> question -> answer -> why.

**Framework**

- `framework.file_rehome_copies` File copies made by `file_rehome` in `finalise()`? -> A reviewed side effect: copy-only, insert-only, idempotent, declared through the `copies_files` marker by all five callers, left in place by `--purge-feature`; `{files}` is watched for every importer without the marker -> one rule for org, cohort_scope, learningplan, classroom and programs (IDN-04).
- `framework.protect_imported_history_pending_enrolments` Unenrol of an imported enrolment? -> Allowed when it has no completion, progress or attendance, on an active path, classroom or program; every row with history stays blocked; on a learning path the admin is shown the course enrolments that remain -> BizLMS allowed the routine action, and nothing is removed automatically (LRN-10).
- `framework.imported_rows_on_admin_pages` Imported rows on existing admin pages? -> Entities other rows reference show on their admin pages; history and log rows nothing references show only behind the feature's imported-history flag; HRMS sync runs get `sentientia.users.imported_sync_history`; evaluation's imported forms and responses are the recorded exception -> one reader rule (XC-IMPORTED-HISTORY-READERS).

**org, org_roles, users**

- `org.crosstenant_platform_role` Who holds the cross-tenant platform role at cutover? -> The role is created by the ADR-031 script with no members; members are added by hand only when Nitin names them -> narrowest reversible default (IDN-05).
- `org_roles.user_without_tenant` A user with no tenant path named on an org-role row? -> Left out; a row with nobody left is skipped (needs-owner `user_without_tenant`) -> ADR-031 decisions 4 and 6, fail closed (IDN-01).
- `users.logindays_erasure` Imported login days on a DPDP erasure request? -> Deleted -> a (user, day) row has no meaning without the user (IDN-06).
- `users.sync_history_visibility` Who sees HRMS sync history? -> Run list tenant-wide; rejected lines only to the uploader and cross-tenant callers -> exact BizLMS parity (IDN-07).

**course_lookups, enrolments**

- `course_lookups.coursedetails_candidate_columns` Write `proficiencylevel` and `credits` into core course columns? -> No, `leave`, counted in preflight -> unverified candidates, no reader for `open_points` (CRS-06, CRS-15).
- `enrolments.bizlms_instances_after_verify` Disable converted BizLMS instances? -> Yes, each one, only when every (user, course) pair keeps an access window at least as wide through manual enrolments, never deleted, undo is one UPDATE -> core grants access through any enabled instance (CRS-01).
- `enrolments.disabled_instance_row_status` A row on a disabled BizLMS instance? -> Converts as suspended -> never give access BizLMS did not give (CRS-02).
- `enrolments.disabled_only_manual_instance` A course whose only manual instance is disabled? -> A new enabled one is added beside it -> learners keep today's access (CRS-03).
- `gap.orphan_enrol_instances` (why corrected) see the G6 line above (XC-G6-WHY).

**recompletion, learning paths, programs, classrooms, skills**

- `recompletion.inferred_reset_without_evidence` Reset time with no evidence? -> The cycle's last recorded evidence + 1 second, never the import time (LRN-01).
- `recompletion.legacy_rows_on_history_page` BizLMS rows on `history.php`? -> Behind the evidence_view flag (LRN-02).
- `recompletion.dpdp_archive_free_text` DPDP erasure and archived free text? -> The record stays, the free text is cleared (LRN-03).
- `recompletion.imported_rule_enable` Enable an imported rule? -> Blocked until engine parity is declared done (LRN-04).
- `learningplan.cover_on_admin_view` Imported cover on the admin page? -> Behind the learner_paths flag (LRN-08).
- `learningplan.user_startdate` A value in `local_learningplan_user.startdate`? -> Preflight blocks (LRN-09).
- `learningplan.stalled_nudge_scope` Stalled-path nudge? -> Native rows on active paths only (LRN-11).
- `program.nameless_with_shortname` A nameless program with a shortname? -> Import under the shortname with a warning (LRN-12).
- `program.delete_imported_level` Delete an imported level? -> Blocked (LRN-13).
- `classroom.cotrainer_sessions` Co-trainer sessions? -> Every session of their own classroom (LRN-15).
- `classroom.trainer_erasure` Trainer erasure? -> Core releases the trainer, DPDP keeps the record against the anonymised user (LRN-16).
- `classroom.new_states_ui` Draft and On hold in the UI? -> Unflagged, because a select that does not list the stored value rewrites it (LRN-17).
- `skills.level_proficiency` (csv filled) see the Skills line above (LRN-07).

**evaluation, request, notifications, cart**

- `evaluation.sticky_anonymity` A form that ever held an anonymous answer? -> Every answer imports anonymous (EV-16).
- `evaluation.legacy_anonymous_linkage` (why widened) -> The legacy-table privacy ADR also covers the import map and the assignment-id order, and must be accepted before any legacy evaluation table is anonymised or dropped; not a cutover precondition (EV-19).
- `evaluation.tenant_editor_fallback` A form with no usable tenant? -> Pathless; never the tenant of whoever last edited it (EV-TENANT).
- `request.pending_stale` A pending request whose requester left or whose item is gone? -> History only, no approver (COMMS-R1).
- `request.comments` (why extended) -> Preflight blocks if rows exist; a decision appends to the note (COMMS-R4).
- `notifications.team_member_copy_body` Manager copies? -> Imported without the body, the member's name scrubbed from the subject (COMMS-N2).
- `notifications.course_link` The course of an imported e-mail? -> Taken from `moduleid` for course templates (COMMS-N3).
- `notifications.deleted_recipient_sent` (wording corrected) see the notifications line above (COMMS-N6).
- `gaps.notification_sender_parity` Notification types with no Sentientia sender? -> Build them behind default-OFF flags; the flips are Nitin's call after UAT (COMMS-N7).
- `cart.credit_balances` and `cart.erpnext_invoices_legal` -> Accepted under delegation as frozen, admin-only history and references only; Airpay Finance NOT consulted (cart.credit_balances, cart.erpnext_invoices_legal, cart.finance_keys_status).

**Recorded without a decisions-file key:** the id-sequence floor (EV-26, "Id strategy" 6); the framework change rule (F-83); the
`catalog` price-source fix (`cart.price_source`: `enrol_fee` is authoritative and `enrol_now()` refuses a priced course); the
withheld-line refund wording (`cart.withheld_line_refund`: state the amounts, never refund automatically); holding native GST tax
invoices until Finance answers six points (`cart.native_tax_invoices`); exam pseudo-courses off the guest storefront (CRS-14);
the rating widget flag (CRS-11); the recorded flag recommendations (CRS-12, COMMS-C2, LRN-06); the response-drilldown, bulk-enrol and
bulk-assign decisions (EV-06, XC-CLS-ENROL, EV-36); and the descriptive acceptance rules `accepted_reasons (org)` (IDN-03) and
`accepted_reasons` (CRS-04), which are not decision keys.

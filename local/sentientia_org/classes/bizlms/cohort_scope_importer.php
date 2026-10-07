<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\copies_files;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032 importer for feature cohort_scope: BizLMS local_groups to local_sentientia_cohort_scope.
 *
 * Mapping doc section 5. The owning plugin is local_sentientia_org, and it depends on the org feature
 * because the tenant path of every row is checked against the organisation tree the org importer fills.
 *
 * What it does, and what it leaves alone:
 *  - One scope row per core cohort (cohortid, tenant path, department ids, who and when). The id strategy
 *    is MAP: nothing outside the import stores a local_groups id, and the target's unique key is the
 *    cohort id.
 *  - Core cohort and cohort_members are not touched: the restored database already holds them. The
 *    importer calls no cohort API and fires no event (cohort_created, cohort_updated and the member events
 *    are what the BizLMS code did).
 *  - local_groups stays in place as the archive. Nothing is written to it.
 *  - Description files: BizLMS kept them under its own component, core serves them from component
 *    `cohort`. finalise() copies them (originals kept). The copy is a reviewed side effect declared through the
 *    copies_files marker (decision IDN-04, signed key framework.file_rehome_copies), not a core write: it is
 *    copy-only, insert-only and idempotent, and --purge-feature leaves it in place.
 *
 * Nothing in Sentientia reads the scope table yet (the mapping doc adds no reader for it), so this
 * feature adds no user-visible surface and no feature flag.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_scope_importer implements importer, copies_files {

    /** Feature key. */
    public const FEATURE = 'cohort_scope';

    /** The one legacy table this feature claims. */
    public const SOURCE = 'local_groups';

    /** The only table this feature writes. */
    public const TARGET = 'local_sentientia_cohort_scope';

    /** Owner decision: what to do with a cohort whose tenant cannot be resolved. */
    public const DECISION_UNRESOLVED = 'tenant.unresolved.cohort_scope';

    /** Plugin version that carries the target table (version.php, db/upgrade.php). */
    public const REQUIRES_VERSION = 2026093002;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_org';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        // Tenant paths are validated against the organisation tree (mapping doc section 2).
        return ['org'];
    }

    public function sources(): array {
        return [
            self::SOURCE => new source_spec(self::SOURCE, true, [],
                ['costcenterid', 'departmentid', 'open_path', 'timemodified', 'usermodified']),
        ];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return [self::TARGET];
    }

    public function core_writes(): array {
        // None. Core cohort rows are read, never written; the description files are copied by finalise() through
        // the framework's file helper, declared by allowed_file_areas() below.
        return [];
    }

    /**
     * The description files finalise() copies: from the two BizLMS components that held them (the edit path first)
     * into core's own cohort description area. The runner lets {files} grow in that target area and nowhere else.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function allowed_file_areas(): array {
        $areas = [];
        foreach (cohort_files::SOURCE_COMPONENTS as $component) {
            $areas[] = [$component, cohort_files::FILEAREA, cohort_files::COMPONENT, cohort_files::FILEAREA];
        }
        return $areas;
    }

    public function tenant_columns(): array {
        return [self::TARGET => 'open_path'];
    }

    public function reasons(): array {
        return [
            // The satellite row points at a cohort that no longer exists: there is nothing to scope.
            new reason('orphan_cohort', false, false),
            // The owner chose to skip (and not import with no path) a cohort whose tenant cannot be resolved.
            new reason('no_tenant', false, false),
            // A second local_groups row for the same cohort, folded into the row that won.
            new reason('dup_cohort_row', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision(self::DECISION_UNRESOLVED,
                'Cohorts whose tenant cannot be resolved: import with no tenant path, visible to cross-tenant callers '
                . 'only (pathless), or skip them',
                true, null, ['pathless', 'skip']),
        ];
    }

    public function atomic(): bool {
        // A few hundred rows at most: one outer transaction, so a crash leaves nothing.
        return true;
    }

    public function steps(): array {
        return [new cohort_scope_step()];
    }

    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $reader = $ctx->legacy;
        if (!$reader->exists(self::SOURCE) || !$reader->has_column(self::SOURCE, 'cohortid')) {
            // The framework blocks a missing required column (missing_column:local_groups.cohortid) itself.
            return $pf;
        }

        $rows = $reader->count(self::SOURCE);
        if ($reader->has_column(self::SOURCE, 'open_path')) {
            $unknown = $reader->count(self::SOURCE, [
                '(t.open_path IS NULL OR t.open_path = :blmzero OR t.open_path = :blmempty)',
                ['blmzero' => '0', 'blmempty' => ''],
            ]);
            $pf->count('rows_with_unknown_open_path', $unknown);
        }
        $duplicates = $rows - $reader->count_groups(self::SOURCE, ['cohortid']);
        if ($duplicates > 0) {
            $pf->warn('duplicate_cohort_rows:' . $duplicates);
        }
        $orphans = (int) $DB->get_field_sql(
            'SELECT COUNT(1) FROM {' . self::SOURCE . '} g
              WHERE NOT EXISTS (SELECT 1 FROM {cohort} c WHERE c.id = g.cohortid)');
        if ($orphans > 0) {
            $pf->warn('rows_without_a_cohort:' . $orphans);
        }

        // The target's unique key is the cohort id. A row in it that this import did not write would fail the
        // first insert for that cohort in the middle of the run; say so before it starts.
        if ($DB->get_manager()->table_exists(self::TARGET)) {
            $stranger = $DB->count_records(self::TARGET) - $DB->count_records(legacymap::TABLE,
                ['feature' => self::FEATURE, 'targettable' => self::TARGET, 'outcome' => 'imported']);
            if ($stranger > 0) {
                $pf->block('target_has_rows_this_import_did_not_write:' . self::TARGET . ':' . $stranger);
            }
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        if ($ctx->dryrun) {
            return [];
        }
        $failures = [];

        // Once the site is open (the runbook sets bizlms_production_open) administrators may delete cohorts and
        // edit their descriptions, so the two checks that compare the import with live core state stop there,
        // like the framework's own map-to-target check (ADR-032, "Parity hooks" 2).
        $open = (int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0;

        if (!$open) {
            $orphans = (int) $DB->get_field_sql(
                'SELECT COUNT(1) FROM {' . self::TARGET . '} s
                  WHERE NOT EXISTS (SELECT 1 FROM {cohort} c WHERE c.id = s.cohortid)');
            if ($orphans > 0) {
                $failures[] = 'scope_row_without_a_cohort:' . $orphans;
            }
        }

        // departmentids is a comma list of whole numbers or NULL, nothing else.
        $bad = 0;
        $after = 0;
        do {
            $page = $ctx->legacy->page(self::TARGET, $after, legacy_reader::MAX_PAGE, ['departmentids']);
            foreach ($page as $id => $row) {
                $after = $id;
                if ($row->departmentids !== null && !preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/', (string) $row->departmentids)) {
                    $bad++;
                }
            }
        } while (count($page) === legacy_reader::MAX_PAGE);
        if ($bad > 0) {
            $failures[] = 'bad_departmentids:' . $bad;
        }

        // The description files are copied in finalise(), which runs after the first verify. Once the feature
        // is complete, a later verify (and the parity check) proves the copy.
        if (!$open && legacymap::feature_complete(self::FEATURE)) {
            $missing = cohort_files::count_missing($ctx);
            if ($missing > 0) {
                $failures[] = 'description_files_not_copied:' . $missing;
            }
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        cohort_files::copy_all($ctx);
        $missing = cohort_files::count_missing($ctx);
        if ($missing > 0) {
            // No marker: the feature is not complete until every description file has its twin.
            throw new bizlms_exception('description_files_not_copied:' . $missing);
        }
    }
}

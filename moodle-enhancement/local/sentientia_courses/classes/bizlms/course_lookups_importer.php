<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032 importer for the course_lookups feature (mapping doc, section 6).
 *
 * Four BizLMS tables, four steps:
 *
 *  - local_course_types      -> local_sentientia_course_type      PRESERVE (course.open_identifiedas lists their ids)
 *  - local_custom_category   -> local_sentientia_course_category  PRESERVE (course.open_categoryid holds their ids)
 *  - local_dashboardcourses  -> local_sentientia_featured_courses MAP, every list exploded and de-duplicated
 *  - local_coursedetails     -> core course.open_* columns, only where empty
 *
 * The last one is a core UPDATE, a reviewed core write (registry::CORE_WRITES_ALLOWED, course, UPDATE only,
 * never update_course(), so no course_updated event). How it fits the frozen framework, because an UPDATE of a
 * core table is not a shape it was written for: the only path to one is a recompute step, and a recompute step
 * works on rows the load steps imported into the importer's OWN tables. So each course that will be filled gets
 * one row in a small trail table, local_sentientia_courses_detailfill (ids and timestamps only, no person), and
 * the fill step then writes the open_* columns of the courses on the trail and records which columns it wrote.
 * That trail is also what an operator would use to put a rehearsal back: --purge-feature refuses a feature that
 * writes a core table, and the columns the trail lists were empty (NULL or 0) before the import.
 *
 * The importer depends on org because two of the tables name an organisation and tenant resolution reads the
 * organisation table the org importer fills.
 *
 * local_moduleconfig and local_filters are configuration of BizLMS pages and are declined (decision
 * course_lookups.declined_config_tables = stay_in_place); their row counts are reported. The columns of
 * local_coursedetails that have no Sentientia home (enrolment dates, duration, prerequisites) stay in the legacy
 * table (decision course_lookups.coursedetails_unhomed_columns = leave_in_legacy_table), and their counts are
 * reported. local_certificate belongs to the certificates gap map and is not claimed here.
 *
 * The importer never writes a legacy table, never deletes a row, fires no event and calls no manager API.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_lookups_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'course_lookups';

    /** Plugin version that carries the three tables (version.php, db/upgrade.php). */
    public const REQUIRES_VERSION = 2026100101;

    /** Target of the course types step. */
    public const TYPE_TABLE = 'local_sentientia_course_type';

    /** Target of the custom categories step. */
    public const CATEGORY_TABLE = 'local_sentientia_course_category';

    /** Target of the dashboard courses step (an existing table of the plugin). */
    public const FEATURED_TABLE = 'local_sentientia_featured_courses';

    /** Target of the course details step: the trail of courses the fill step writes. */
    public const LEDGER = 'local_sentientia_courses_detailfill';

    /** Cache definitions of the catalogue plugin that hold course cards. */
    private const CATALOG_CACHES = ['in_progress', 'trending', 'new_courses', 'categories'];

    /** Rows read per page by preflight. */
    private const PAGE = 5000;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_courses';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        return ['org'];
    }

    public function sources(): array {
        // None is required: the four tables belong to four BizLMS plugins and any of them may be missing from a
        // snapshot. A table that is missing while others exist is a warning in preflight; all missing means
        // nothing to import. The step that reads a missing table records itself as not applicable.
        return [
            'local_course_types' => new source_spec('local_course_types', false, ['active' => [0 => 'disabled', 1 => 'enabled']]),
            'local_custom_category' => new source_spec('local_custom_category', false),
            'local_dashboardcourses' => new source_spec('local_dashboardcourses', false),
            'local_coursedetails' => new source_spec('local_coursedetails', false),
        ];
    }

    public function declined_tables(): array {
        return [
            'local_moduleconfig' => 'configuration of which cost centres may use a module (BizLMS page settings), not history: '
                . 'it stays in place and is not read by Sentientia; the row count is reported',
            'local_filters' => 'configuration of BizLMS plugin filters, not history: it stays in place and is not read by '
                . 'Sentientia; the row count is reported',
        ];
    }

    public function target_tables(): array {
        return [self::TYPE_TABLE, self::CATEGORY_TABLE, self::FEATURED_TABLE, self::LEDGER];
    }

    public function core_writes(): array {
        return [
            'course' => 'course_lookups: the open_* backfill from local_coursedetails; UPDATE only, only columns that are '
                . 'NULL, empty or 0, never update_course() (mapping doc, section 6)',
        ];
    }

    public function tenant_columns(): array {
        return [
            self::TYPE_TABLE => 'tenant_path',
            self::CATEGORY_TABLE => 'tenant_path',
        ];
    }

    public function reasons(): array {
        return [
            // A featured list or a course-details row names a course that no longer exists.
            new reason('course_missing', false, false),
            // A featured list whose usable courses all belong to an earlier list.
            new reason('duplicate_course', false, false),
            // A featured list with no usable course id at all.
            new reason('empty_course_list', false, false),
            // A featured list whose courses name a tenant that cannot be resolved (costcenterid 0 would be global),
            // or a lookup row skipped under tenant.unresolved.course_lookups = skip. The owner accepts the count.
            new reason('tenant_unresolved', false, true),
            // A course-details row with nothing that could fill an empty course column.
            new reason('nothing_to_fill', false, false),
            // A second local_coursedetails row of a course the first one already fills.
            new reason('duplicate_course_row', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision('tenant.unresolved.course_lookups',
                'Rows whose tenant cannot be resolved: imported with no tenant path (pathless) or skipped',
                true, null, ['pathless', 'skip']),
            new decision('course_lookups.featured_scope',
                'Featured courses: re-homed per tenant by the course path (rehome) or global as in BizLMS',
                true, null, ['rehome', 'global']),
            new decision('course_lookups.coursedetails_unhomed_columns',
                'local_coursedetails enrolment dates, duration and prerequisites have no Sentientia column: left in the legacy table',
                true, null, ['leave_in_legacy_table']),
            new decision('course_lookups.declined_config_tables',
                'local_moduleconfig and local_filters are configuration: they stay in place',
                true, null, ['stay_in_place']),
            // The map calls proficiencylevel -> open_level and credits -> open_points "candidates, verify on data".
            // Until the owner has seen them on the live copy they are counted, not written.
            new decision('course_lookups.coursedetails_candidate_columns',
                'local_coursedetails proficiencylevel -> open_level and credits -> open_points: leave (counted, not written) or fill',
                false, 'leave', ['leave', 'fill']),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    public function steps(): array {
        return [
            new course_types_step(),
            new course_categories_step(),
            new featured_courses_step(),
            new course_details_step(),
            new course_details_fill_step(),
        ];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();

        foreach (array_keys($this->sources()) as $table) {
            if (!$ctx->legacy->exists($table)) {
                $pf->warn('claimed_table_missing:' . $table);
            }
        }
        foreach (array_keys($this->declined_tables()) as $table) {
            if ($ctx->legacy->exists($table)) {
                $pf->count('declined_rows:' . $table, $ctx->legacy->count($table));
            }
        }

        $coursecolumns = $ctx->legacy->columns('course');
        foreach (['open_identifiedas', 'open_categoryid'] as $column) {
            if (!in_array($column, $coursecolumns, true)) {
                $pf->warn('course_column_missing:' . $column);
            }
        }

        $this->preflight_tenants($pf, $ctx, 'local_course_types', 'orgid', false);
        $this->preflight_tenants($pf, $ctx, 'local_custom_category', 'costcenterid', true);
        if ($ctx->legacy->exists('local_dashboardcourses')) {
            $this->preflight_featured($pf, $ctx);
        }
        if ($ctx->legacy->exists('local_coursedetails')) {
            $this->preflight_details($pf, $ctx);
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        if ($ctx->dryrun) {
            return [];
        }
        $failures = [];
        $open = (int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0;
        // A target row the import created or adopted. A featured sub-row (course:<id>) counts: it is a row of its own.
        $imported = "m.feature = :f AND m.outcome IN ('imported', 'adopted')";

        // Types: the five built-in ids are protected and no other imported type is.
        $types = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::TYPE_TABLE . '} t
               JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = t.id
              WHERE ' . $imported . '
                AND ((t.id BETWEEN 1 AND ' . course_types_step::LAST_PROTECTED_ID . ' AND t.protected <> 1)
                  OR (t.id > ' . course_types_step::LAST_PROTECTED_ID . ' AND t.protected <> 0))',
            ['f' => self::FEATURE, 'tt' => self::TYPE_TABLE]);
        if ($types > 0) {
            $failures[] = 'protected_flag_wrong_on_imported_types:' . $types;
        }

        // Featured: every imported row is on the global list or on a registered tenant's list.
        $homes = $DB->get_records_sql(
            'SELECT f.costcenterid AS k, COUNT(1) AS n FROM {' . self::FEATURED_TABLE . '} f
               JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = f.id
              WHERE ' . $imported . '
           GROUP BY f.costcenterid',
            ['f' => self::FEATURE, 'tt' => self::FEATURED_TABLE]);
        foreach ($homes as $home) {
            if ((int) $home->k === 0) {
                continue;
            }
            try {
                \local_sentientia_platform\tenant::assert_valid((int) $home->k);
            } catch (\Throwable $e) {
                $failures[] = 'featured_costcenterid_is_not_a_registered_tenant:' . (int) $home->k . ' rows=' . (int) $home->n;
            }
        }
        // One course appears once per list among the imported rows.
        $twice = $DB->get_records_sql(
            'SELECT MIN(f.id) AS k, COUNT(1) AS n FROM {' . self::FEATURED_TABLE . '} f
               JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = f.id
              WHERE ' . $imported . '
           GROUP BY f.courseid, f.costcenterid
             HAVING COUNT(1) > 1',
            ['f' => self::FEATURE, 'tt' => self::FEATURED_TABLE]);
        if ($twice) {
            $failures[] = 'featured_course_imported_twice_on_one_list:' . count($twice);
        }
        if (!$open) {
            // Skipped once the site is open: a course may be deleted after go-live, and that is not an import failure.
            $gone = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . self::FEATURED_TABLE . '} f
                   JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = f.id
                  WHERE ' . $imported . '
                    AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = f.courseid)',
                ['f' => self::FEATURE, 'tt' => self::FEATURED_TABLE]);
            if ($gone > 0) {
                $failures[] = 'featured_rows_for_a_course_that_does_not_exist:' . $gone;
            }
        }

        // The trail and the map agree: one imported map row per trail row, pointing at it.
        $trail = $DB->count_records(self::LEDGER);
        $mapped = $DB->count_records(legacymap::TABLE, [
            'feature' => self::FEATURE, 'sourcetable' => 'local_coursedetails', 'subkey' => '', 'outcome' => 'imported',
        ]);
        if ($trail !== $mapped) {
            $failures[] = "trail_rows_differ_from_imported_map_rows: trail={$trail} imported={$mapped}";
        }
        $unpaired = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . legacymap::TABLE . '} m
              WHERE m.feature = :f AND m.sourcetable = :st AND m.subkey = :sk AND m.outcome = :oc
                AND NOT EXISTS (SELECT 1 FROM {' . self::LEDGER . '} r WHERE r.id = m.targetid AND r.detailid = m.sourceid)',
            ['f' => self::FEATURE, 'st' => 'local_coursedetails', 'sk' => '', 'oc' => 'imported']);
        if ($unpaired > 0) {
            $failures[] = 'imported_map_rows_without_a_matching_trail_row:' . $unpaired;
        }

        // Every column the trail says it filled is still filled. Skipped once the site is open: an administrator may
        // clear a course field after go-live, and that is not an import failure.
        if (!$open) {
            $rows = $DB->get_records_sql('SELECT id, courseid, filledcols FROM {' . self::LEDGER . "} WHERE filledcols <> ''", [], 0, 100000);
            $courseids = [];
            foreach ($rows as $row) {
                $courseids[] = (int) $row->courseid;
            }
            $courses = $ctx->legacy->fetch('course', $courseids, course_details_plan::course_columns());
            foreach ($rows as $row) {
                $course = $courses[(int) $row->courseid] ?? null;
                foreach (explode(',', (string) $row->filledcols) as $column) {
                    if ($course === null || !property_exists($course, $column)
                            || trim((string) $course->{$column}) === '' || trim((string) $course->{$column}) === '0') {
                        $failures[] = 'trail_column_not_filled:' . (int) $row->courseid . '.' . $column;
                    }
                }
            }
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        // The course cards the catalogue caches show the course type and the open_* fields this import fills.
        // Best effort: a catalogue that is not installed, or a definition that is not there, is not a failure.
        if (\core_component::get_component_directory('local_sentientia_catalog') === null) {
            return;
        }
        foreach (self::CATALOG_CACHES as $definition) {
            try {
                \cache_helper::purge_by_definition('local_sentientia_catalog', $definition);
            } catch (\Throwable $e) {
                debugging('course_lookups: could not purge the catalogue cache ' . $definition, DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Count the rows of a lookup table whose organisation cannot be resolved, and the rows that name none.
     *
     * @param preflight $pf
     * @param context $ctx
     * @param string $table Legacy table.
     * @param string $column Column holding the cost centre id.
     * @param bool $rootonly
     * @return void
     */
    private function preflight_tenants(preflight $pf, context $ctx, string $table, string $column, bool $rootonly): void {
        if (!$ctx->legacy->exists($table)) {
            return;
        }
        $unresolved = 0;
        $none = 0;
        $after = 0;
        do {
            $page = $ctx->legacy->page($table, $after, self::PAGE, [$column]);
            foreach ($page as $id => $row) {
                [$path, $method] = lookup_tenant::of_costcenter($ctx, (int) ($row->{$column} ?? 0), $rootonly);
                if ($method === 'unresolved') {
                    $unresolved++;
                } else if ($path === null) {
                    $none++;
                }
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        $pf->count('rows_without_an_organisation:' . $table, $none);
        $pf->count('rows_with_an_unresolved_organisation:' . $table, $unresolved);
        if ($unresolved > 0) {
            // The decision tenant.unresolved.course_lookups says what happens to them; this is the count for the owner.
            $pf->warn('unresolved_organisation:' . $table . ':' . $unresolved);
        }
    }

    /**
     * Count what the featured lists hold: distinct courses, missing courses, ignored tokens.
     *
     * @param preflight $pf
     * @param context $ctx
     * @return void
     */
    private function preflight_featured(preflight $pf, context $ctx): void {
        $courses = [];
        $invalid = 0;
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_dashboardcourses', $after, self::PAGE, ['courseids']);
            foreach ($page as $id => $row) {
                [$ids, $bad] = featured_courses_step::parse_course_ids($row->courseids ?? '');
                $invalid += $bad;
                foreach ($ids as $courseid) {
                    $courses[$courseid] = true;
                }
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);

        $missing = 0;
        foreach (array_keys($courses) as $courseid) {
            if (!$ctx->lookups->course_exists($courseid)) {
                $missing++;
            }
        }
        $pf->count('featured_distinct_courses', count($courses));
        $pf->count('featured_missing_courses', $missing);
        $pf->count('featured_ignored_tokens', $invalid);
    }

    /**
     * Count what local_coursedetails would fill, what the two candidate columns would fill, and the columns that
     * have no home.
     *
     * @param preflight $pf
     * @param context $ctx
     * @return void
     */
    private function preflight_details(preflight $pf, context $ctx): void {
        $fillable = 0;
        $levels = 0;
        $points = 0;
        $unhomed = 0;
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_coursedetails', $after, self::PAGE);
            $courseids = [];
            foreach ($page as $row) {
                $courseids[] = (int) ($row->courseid ?? 0);
            }
            $courses = $ctx->legacy->fetch('course', $courseids, course_details_plan::course_columns());
            foreach ($page as $id => $row) {
                $course = $courses[(int) ($row->courseid ?? 0)] ?? null;
                $plan = course_details_plan::build($row, $course, $ctx);
                if ($plan['fields']) {
                    $fillable++;
                }
                if (isset($plan['candidates']['open_level'])) {
                    $levels++;
                }
                if (isset($plan['candidates']['open_points'])) {
                    $points++;
                }
                if ((int) ($row->enrollstartdate ?? 0) > 0 || (int) ($row->enrollenddate ?? 0) > 0
                        || !in_array(trim((string) ($row->duration ?? '')), ['', '0'], true)
                        || trim((string) ($row->prerequisite_courses ?? '')) !== '') {
                    $unhomed++;
                }
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);

        $pf->count('coursedetails_rows_that_can_fill_a_course', $fillable);
        $pf->count('coursedetails_candidate_open_level_not_written', $levels);
        $pf->count('coursedetails_candidate_open_points_not_written', $points);
        $pf->count('coursedetails_rows_with_columns_left_in_the_legacy_table', $unhomed);
    }
}

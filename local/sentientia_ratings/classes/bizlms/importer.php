<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * The ratings feature of the BizLMS import (ADR-032, mapping doc section 20).
 *
 * Sources: the three tables BizLMS's local_ratings plugin kept what learners said about a course, classroom,
 * programme or learning path:
 *
 *   local_rating  a star rating            -> local_sentientia_ratings           (one per user, item, area)
 *   local_comment a free-text review       -> local_sentientia_ratings_reviews   (as many as the learner left)
 *   local_like    a like or dislike        -> local_sentientia_ratings_reactions (one per user, item, area)
 *
 * Two derived tables are declined: local_ratings_likes is a cache BizLMS rewrote on every write (it is the
 * parity oracle, see oracle), and block_trending_modules holds a third copy of the averages. Neither is read by
 * any Sentientia reader and neither is imported.
 *
 * Every id is MAP: nothing outside this feature stores a rating, review or reaction id. An item that is a
 * course keeps its core id; a classroom, programme or learning plan is resolved through that feature's map,
 * so this importer depends on classroom, program and learningplan. The tables carry no tenant column, as in
 * BizLMS (averages and counts are site-wide per item); the review reader limits the reviewers it lists to the
 * viewer's tenant. The preflight reports the raters whose tenant differs from their course's.
 *
 * Owner choices (all signed in the decisions file): invalid rows are skipped and reported, blank reviews are
 * imported and hidden by the reader, deleted users' rows are kept, and the certification area, which has no
 * Sentientia entity, is skipped and reported. The dislike count the reader shows is a reader choice, not an
 * import one.
 *
 * Nothing is sent, enrolled, completed, scored or recounted: the import never calls rating_manager (it stamps
 * time()), and it writes none of the derived tables.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract {

    /** Feature key. */
    public const FEATURE = 'ratings';

    /** Plugin version that carries the two new tables this importer writes to. */
    public const REQUIRES_VERSION = 2026093001;

    /** Page size of the preflight's own scans. */
    private const PAGE = 2000;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_ratings';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        // The items of three areas are resolved through these features' maps, even where their ids were kept.
        return ['classroom', 'program', 'learningplan'];
    }

    public function sources(): array {
        return [
            'local_rating' => new source_spec('local_rating'),
            // time is the 2013-era creation time column that the table's install file no longer declares.
            'local_comment' => new source_spec('local_comment', true, [], ['time']),
            'local_like' => new source_spec('local_like'),
        ];
    }

    public function declined_tables(): array {
        return [
            'local_ratings_likes' => 'a derived cache of averages and counts that BizLMS rewrote on every write; '
                . 'it is the parity oracle and is never imported',
            'block_trending_modules' => 'a derived third copy of the averages, fed by the same code; no Sentientia '
                . 'reader asks for it',
        ];
    }

    public function target_tables(): array {
        return [ratings_step::TARGET, reviews_step::TARGET, reactions_step::TARGET];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        // None: ratings, reviews and reactions are site-wide per item, as in BizLMS.
        return [];
    }

    public function reasons(): array {
        return [
            // Rows that could not be imported: the owner accepts the counts after the rehearsal.
            new reason('invalid_rating', false, true),
            new reason('invalid_reaction', false, true),
            new reason('orphan_user', false, true),
            new reason('orphan_item', false, true),
            new reason('unknown_area', false, true),
            // Rows that stay in the legacy table, or became part of another row, on purpose.
            new reason('user_deleted', false, false),
            new reason('blank_review', false, false),
            new reason('dup_natural_key', false, false),
            new reason('native_row_kept', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision('ratings.invalid_rows',
                'Rating rows that are not a whole number from 1 to 5 (and unknown, missing or guest rows): '
                . 'only skipping and reporting them is built; they stay in the legacy table',
                true, null, ['skip']),
            new decision('ratings.blank_reviews',
                'Reviews with no text: import them (the reader hides them) or leave them in the legacy table',
                true, null, ['import_hidden', 'skip']),
            new decision('ratings.deleted_users',
                'Ratings, reviews and reactions of deleted users: keep them (averages stay what BizLMS showed) or skip',
                true, null, ['keep', 'skip']),
            new decision('ratings.certification_area',
                'Rows under the certification area, which has no Sentientia entity: skipped and reported',
                true, null, ['skip_and_report']),
        ];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        return [new ratings_step(), new reviews_step(), new reactions_step()];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();
        $this->preflight_values($ctx, $pf);
        $this->preflight_review_caveats($ctx, $pf);
        $this->preflight_moduleid($ctx, $pf);
        $this->preflight_declined($ctx, $pf);
        $this->preflight_cross_tenant($ctx, $pf);

        $cache = oracle::cache_vs_legacy();
        $pf->count('cache_rows', $cache['cache_rows']);
        if ($cache['disagree'] > 0) {
            $pf->warn('cache_disagrees_with_legacy_rows:' . $cache['disagree']);
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];

        // Every imported star rating is a whole number from 1 to 5.
        $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . ratings_step::TARGET . '} x WHERE '
            . self::imported_condition('tt') . ' AND (x.rating < 1 OR x.rating > 5)',
            ['feature' => self::FEATURE, 'tt' => ratings_step::TARGET]);
        if ($n > 0) {
            $failures[] = 'rating_out_of_range:' . $n;
        }

        [$notin, $areaparams] = $DB->get_in_or_equal(area_map::target_areas(), SQL_PARAMS_NAMED, 'blmta', false);
        foreach ($this->target_tables() as $table) {
            $base = ['feature' => self::FEATURE, 'tt' => $table];

            // An imported row names one of the four Sentientia areas, and a real item and user.
            $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . $table . '} x WHERE '
                . self::imported_condition('tt')
                . " AND (x.ratearea {$notin} OR x.itemid < 1 OR x.userid < 2)", $base + $areaparams);
            if ($n > 0) {
                $failures[] = 'row_outside_the_map:' . $table . ':' . $n;
            }

            // The course an imported course row is about still exists.
            $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . $table . '} x'
                . ' LEFT JOIN {course} c ON c.id = x.itemid WHERE ' . self::imported_condition('tt')
                . ' AND x.ratearea = :area AND c.id IS NULL',
                $base + ['area' => area_map::target_area('local_courses')]);
            if ($n > 0) {
                $failures[] = 'course_missing:' . $table . ':' . $n;
            }

            // The user an imported row belongs to exists (a deleted user's row is kept, a missing one never imported).
            $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . $table . '} x'
                . ' LEFT JOIN {user} u ON u.id = x.userid WHERE ' . self::imported_condition('tt')
                . ' AND u.id IS NULL', $base);
            if ($n > 0) {
                $failures[] = 'user_missing:' . $table . ':' . $n;
            }
        }
        return $failures;
    }

    /**
     * SQL for "a target row aliased x that this import created or adopted", with the named parameters
     * :feature and :<table param> that the caller supplies.
     *
     * @param string $tableparam Name of the parameter that holds the target table.
     * @return string
     */
    private static function imported_condition(string $tableparam): string {
        return 'EXISTS (SELECT 1 FROM {local_sentientia_legacymap} m WHERE m.feature = :feature'
            . " AND m.targettable = :{$tableparam} AND m.targetid = x.id AND m.outcome IN ('imported', 'adopted'))";
    }

    public function finalise(context $ctx): void {
        // Nothing to do outside a transaction: every step is MAP, so there is no sequence to reset, and the
        // runner writes the completion marker itself.
    }

    /**
     * What the three tables hold, as histograms, so the owner sees every value before the rehearsal is accepted.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_values(context $ctx, preflight $pf): void {
        global $DB;
        $columns = [
            ['local_rating', 'ratearea'], ['local_rating', 'rating'],
            ['local_comment', 'commentarea'],
            ['local_like', 'likearea'], ['local_like', 'likestatus'],
        ];
        foreach ($columns as [$table, $column]) {
            if (!$ctx->legacy->exists($table) || !$ctx->legacy->has_column($table, $column)) {
                continue;
            }
            $values = [];
            $rows = $DB->get_records_sql(fingerprint::value_histogram_sql($table, $column), null, 0, 60);
            foreach ($rows as $row) {
                $values[$row->v === null ? '' : (string) $row->v] = (int) $row->n;
            }
            $pf->histogram($table . '.' . $column, $values);
        }
        if ($ctx->legacy->exists('local_rating')) {
            // The rows the import will skip under ratings.invalid_rows.
            $pf->count('rating_rows_invalid',
                $ctx->legacy->count('local_rating', ['t.rating IS NULL OR t.rating < 1 OR t.rating > 5', []]));
        }
    }

    /**
     * What the owner must know about BizLMS reviews before reading the import as complete (mapping doc section 20,
     * verification corrections): the review's author was whatever the caller of the save web service said, not the
     * logged-in user, and a review could be deleted by anyone who knew its URL, with no login. Both stay as
     * report lines, because nothing in the data can tell the rows apart.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_review_caveats(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->exists('local_comment')) {
            return;
        }
        $rows = $ctx->legacy->count('local_comment');
        if ($rows > 0) {
            $pf->warn('review_author_asserted_not_authenticated:' . $rows);
            $pf->warn('review_history_may_be_incomplete:unauthenticated_delete');
        }
    }

    /**
     * moduleid is not copied (no live writer). Count it, and say so when it differs from the item.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_moduleid(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->exists('local_rating') || !$ctx->legacy->has_column('local_rating', 'moduleid')) {
            return;
        }
        $pf->count('rating_moduleid_set', $ctx->legacy->count('local_rating', ['t.moduleid IS NOT NULL', []]));
        $differs = $ctx->legacy->count('local_rating', ['t.moduleid IS NOT NULL AND t.moduleid <> t.itemid', []]);
        if ($differs > 0) {
            $pf->warn('rating_moduleid_differs_from_itemid:' . $differs);
        }
    }

    /**
     * Rows of the declined tables, so the parity report can show what stays behind.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_declined(context $ctx, preflight $pf): void {
        foreach (array_keys($this->declined_tables()) as $table) {
            if ($ctx->legacy->exists($table)) {
                $pf->count('declined_rows:' . $table, $ctx->legacy->count($table));
            }
        }
    }

    /**
     * Raters whose tenant is not their course's: counted and kept (the tables are site-wide per item, as in
     * BizLMS), so the owner sees how far the review reader's tenant limit will cut a list.
     *
     * Only course items have a tenant of their own on the restored database (course.open_path). The user's
     * tenant is their CURRENT one: the source stores none.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_cross_tenant(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->has_column('course', 'open_path')) {
            return;
        }
        $roots = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('course', $after, self::PAGE, ['id', 'open_path']);
            foreach ($page as $id => $course) {
                $path = tenant_resolver::normalise($course->open_path === null ? null : (string) $course->open_path);
                if ($path !== null) {
                    $roots[(int) $id] = (int) explode('/', ltrim($path, '/'))[0];
                }
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        if (!$roots) {
            return;
        }

        $tables = [
            ['local_rating', 'ratearea', 'ratings'],
            ['local_comment', 'commentarea', 'reviews'],
            ['local_like', 'likearea', 'reactions'],
        ];
        foreach ($tables as [$table, $areacolumn, $label]) {
            if (!$ctx->legacy->exists($table)) {
                continue;
            }
            $cross = 0;
            $after = 0;
            do {
                $page = $ctx->legacy->page($table, $after, self::PAGE, ['id', 'itemid', 'userid'],
                    ["t.{$areacolumn} = :blmarea", ['blmarea' => 'local_courses']]);
                foreach ($page as $id => $row) {
                    $courseroot = $roots[(int) $row->itemid] ?? 0;
                    $userroot = $ctx->tenant->root_of_user((int) $row->userid);
                    if ($courseroot > 0 && $userroot > 0 && $courseroot !== $userroot) {
                        $cross++;
                    }
                    $after = (int) $id;
                }
            } while (count($page) === self::PAGE);
            if ($cross > 0) {
                $pf->warn('cross_tenant_' . $label . ':' . $cross);
            }
        }
    }
}

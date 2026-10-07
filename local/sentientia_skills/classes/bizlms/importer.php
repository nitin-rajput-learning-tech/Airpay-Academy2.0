<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The skills feature of the BizLMS import (ADR-032, mapping doc section 14).
 *
 * Source: the BizLMS skill repository (categories, skills, course levels, learners' interests) plus the facts
 * BizLMS kept on core tables: which skill and level a course teaches (course.open_skill, open_level) and who
 * completed it (course_completions, and the recompletion plugin's archive of completions it reset).
 * Targets: local_sentientia_skill_cats, _skills, _course_levels, _course_skills, _user_skills, _user_skill_hist
 * and _skill_interest. The skillmatrix table is declined: it had no writer and BizLMS showed it only when a
 * plugin directory that is absent from the snapshot existed (decision skills.skillmatrix = decline).
 *
 * Owner choices this importer supports, and refuses to run without (preflight): one shared catalogue
 * (skills.catalogue_scope = shared; tenant-scoped readers are not built), category merge by exact name, the
 * 48-skill seed kept, the level map written out in the decisions file (skills.level_proficiency.csv: no
 * csv, no import), the source label, history from the recompletion archive, skillmatrix declined, interests
 * read behind the skillsrecs flag.
 *
 * The feature is applicable when a BizLMS skill table exists. course_completions is claimed as a source only
 * then, so a Sentientia site that never ran BizLMS finds the feature not applicable and never runs a step
 * over its own completions.
 *
 * Depends on org (the catalogue rows carry tenant paths, resolved against the organisation table) and on
 * recompletion (the archive of reset completions is read as history, so that feature's importer has run).
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract {

    /** Feature key. */
    public const FEATURE = 'skills';

    /** Plugin version that carries the schema this importer writes to. */
    public const REQUIRES_VERSION = 2026093001;

    /** Legacy tables whose presence means this is a BizLMS database with skills. */
    private const LEGACY_TABLES = ['local_skill_categories', 'local_skill', 'local_course_levels', 'local_interested_skills'];

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_skills';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        return ['org', 'recompletion'];
    }

    public function sources(): array {
        $sources = [
            'local_skill_categories' => new source_spec('local_skill_categories', true, [], ['costcenterid']),
            'local_skill' => new source_spec('local_skill', true, [], ['costcenterid']),
            'local_course_levels' => new source_spec('local_course_levels', true, [], ['costcenterid']),
            'local_interested_skills' => new source_spec('local_interested_skills', false,
                ['active' => [0 => 'withdrawn', 1 => 'active']], ['open_costcenterid']),
        ];
        if ($this->holds_skills()) {
            // Core table, live data: the registry's one-owner rule is about legacy tables, and nobody else
            // reads completions as a source.
            $sources['course_completions'] = new source_spec('course_completions', false);
        }
        return $sources;
    }

    public function declined_tables(): array {
        return [
            'local_skillmatrix' => 'no writer and no live reader: BizLMS showed it only when a plugin directory that '
                . 'is absent from the snapshot existed (decision skills.skillmatrix = decline)',
        ];
    }

    public function target_tables(): array {
        return [
            'local_sentientia_course_levels', 'local_sentientia_skill_cats', 'local_sentientia_skills',
            'local_sentientia_course_skills', 'local_sentientia_user_skills', 'local_sentientia_user_skill_hist',
            'local_sentientia_skill_interest',
        ];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [
            'local_sentientia_course_levels' => 'open_path',
            'local_sentientia_skill_cats' => 'open_path',
            'local_sentientia_skills' => 'open_path',
        ];
    }

    public function reasons(): array {
        return [
            // Rows that could not be imported: the owner accepts the counts after the rehearsal.
            new reason('empty_name', false, true),
            new reason('category_missing', false, true),
            new reason('tenant_unresolved', false, true),
            new reason('skill_not_imported', false, true),
            new reason('orphan_user', false, true),
            new reason('no_valid_skills', false, true),
            // Rows that stay in the legacy table, or became part of another row, on purpose.
            new reason('no_course_links', false, false),
            new reason('no_skill_courses', false, false),
            new reason('user_deleted', false, false),
            new reason('withdrawn', false, false),
            new reason('dup_learner_row', false, false),
            new reason('seed_category_match', false, false),
            new reason('native_row_kept', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision(catalogue_tenant::DECISION,
                'Skill catalogue rows whose tenant cannot be decided: import with no path (shared catalogue) or skip',
                false, 'pathless', ['pathless', 'skip']),
            new decision('skills.catalogue_scope',
                'Skills catalogue scope. Only one shared catalogue is built; a tenant-scoped one needs readers that do not exist',
                true, null, ['shared']),
            new decision('skills.merge_categories', 'Merge a legacy category into a seeded one by exact name, or never',
                true, null, ['exact_name', 'none']),
            new decision('skills.seed_rows', 'Keep the platform seed rows; imported skills are never merged into them',
                true, null, ['keep']),
            new decision('skills.level_proficiency',
                'Course level id to skill level 1..5: the approved name heuristic, and the csv generated from the rehearsal preflight',
                true),
            new decision('skills.source_label', 'Source label written on imported user skill rows and their history',
                true, null, ['import', 'course']),
            new decision('skills.history_from_archive', 'Grant skill history from the recompletion archive too', true),
            new decision('skills.skillmatrix', 'local_skillmatrix: only declining it is built',
                true, null, ['decline']),
            new decision('skills.interests', 'Interests are imported and read behind the existing skillsrecs flag',
                true, null, ['reader_behind_flag']),
        ];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        $steps = [new levels_step(), new categories_step(), new skills_step(), new course_links_step()];
        if ($this->holds_skills()) {
            $steps[] = new user_skills_step();
        }
        $steps[] = new interests_step();
        return $steps;
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();
        $this->preflight_level_map($ctx, $pf);
        $this->preflight_catalogue($ctx, $pf);
        $this->preflight_courses($ctx, $pf);
        $this->preflight_interests($ctx, $pf);
        $this->preflight_archive($ctx, $pf);

        if ($ctx->legacy->exists('local_skillmatrix')) {
            $pf->count('declined_rows:local_skillmatrix', $ctx->legacy->count('local_skillmatrix'));
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];

        $imported = "EXISTS (SELECT 1 FROM {local_sentientia_legacymap} m WHERE m.feature = :feature"
            . " AND m.targettable = :%s AND m.targetid = %s.id AND m.outcome IN ('imported', 'adopted'))";

        // Every imported level has a skill level the owner chose.
        $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {local_sentientia_course_levels} l WHERE '
            . sprintf($imported, 'tl', 'l') . ' AND (l.proficiency IS NULL OR l.proficiency < 1 OR l.proficiency > 5)',
            ['feature' => self::FEATURE, 'tl' => 'local_sentientia_course_levels']);
        if ($n > 0) {
            $failures[] = 'levels_without_proficiency:' . $n;
        }

        // No skill without a category to show it in.
        $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {local_sentientia_skills} s'
            . ' LEFT JOIN {local_sentientia_skill_cats} c ON c.id = s.categoryid WHERE c.id IS NULL');
        if ($n > 0) {
            $failures[] = 'skills_without_category:' . $n;
        }

        // Imported course links teach a level in range.
        $n = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {local_sentientia_course_skills} cs WHERE '
            . sprintf($imported, 'tc', 'cs') . ' AND (cs.teaches_level < 1 OR cs.teaches_level > 5)',
            ['feature' => self::FEATURE, 'tc' => 'local_sentientia_course_skills']);
        if ($n > 0) {
            $failures[] = 'course_skills_level_out_of_range:' . $n;
        }

        // Every course a legacy skill points at, for a skill that was imported, has its link.
        if ($ctx->legacy->exists('course') && $ctx->legacy->has_column('course', 'open_skill')) {
            $n = (int) $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {course} c
                   JOIN {local_sentientia_legacymap} m ON m.feature = :feature AND m.sourcetable = :st AND m.subkey = ''
                        AND m.sourceid = c.open_skill AND m.outcome IN ('imported', 'adopted')
              LEFT JOIN {local_sentientia_course_skills} cs ON cs.courseid = c.id AND cs.skillid = m.targetid
                  WHERE c.open_skill > 0 AND c.id > 1 AND cs.id IS NULL",
                ['feature' => self::FEATURE, 'st' => 'local_skill']);
            if ($n > 0) {
                $failures[] = 'course_links_missing:' . $n;
            }
        }

        // An imported learner row holds exactly the highest level its imported history reached.
        $n = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_sentientia_user_skills} us
              WHERE " . sprintf($imported, 'tu', 'us') . "
                AND (us.current_level < 1 OR us.current_level > 5
                     OR us.current_level <> COALESCE((SELECT MAX(h.new_level)
                                                        FROM {local_sentientia_user_skill_hist} h
                                                        JOIN {local_sentientia_legacymap} hm ON hm.feature = :feature2
                                                             AND hm.targettable = :th AND hm.targetid = h.id
                                                             AND hm.outcome = 'imported'
                                                       WHERE h.userid = us.userid AND h.skillid = us.skillid), 0))",
            ['feature' => self::FEATURE, 'feature2' => self::FEATURE, 'tu' => 'local_sentientia_user_skills',
                'th' => 'local_sentientia_user_skill_hist']);
        if ($n > 0) {
            $failures[] = 'user_skill_level_differs_from_history:' . $n;
        }

        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing outside a transaction to do: the runner resets the sequence of the one PRESERVE target
        // (course levels) and writes the completion marker itself.
    }

    /**
     * Does this database hold a BizLMS skill table?
     *
     * @return bool
     */
    private function holds_skills(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (self::LEGACY_TABLES as $table) {
            if ($dbman->table_exists($table)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The level map must be written out in the decisions file whenever there are levels to map. No csv, or a csv
     * that misses a level, blocks the feature with a code that names the fix; the suggestion is the owner's
     * approved heuristic applied to each level name, to paste into the file after review. A csv entry that differs
     * from the heuristic's answer for the level's current name is a warning on every run
     * (level_proficiency_differs_from_rule), so a reviewed deviation and a renamed level are never silent.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_level_map(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->exists('local_course_levels')) {
            return;
        }
        try {
            $value = $ctx->decision('skills.level_proficiency');
        } catch (blocked $e) {
            $pf->block($e->getMessage());
            return;
        }
        $levels = $this->all_levels($ctx);
        $pf->count('levels', count($levels));
        if (!$levels) {
            return;
        }

        $parsed = level_map::parse($value);
        $missing = [];
        foreach ($levels as $id => $name) {
            if (!isset($parsed['map'][$id])) {
                $missing[] = $id;
            }
        }
        foreach ($parsed['problems'] as $problem) {
            $pf->block($problem);
        }
        if (!$parsed['problems'] && $missing) {
            $pf->block('level_proficiency_csv_incomplete:' . implode(',', $missing));
        }
        // Owner decision LRN-07 (2026-10-07): the signed csv is the approved name rule applied to the April levels and
        // reviewed by hand. Every entry that differs from what the rule gives for the level's CURRENT name is reported
        // on every run: the reviewed deviation (April level 16, the plural of "basic", mapped to 2) and any level
        // renamed or re-pointed since the csv was written show up before the hash is pinned.
        $differs = [];
        foreach ($levels as $id => $name) {
            if (isset($parsed['map'][$id]) && $parsed['map'][$id] !== level_map::suggest($name, $value)) {
                $differs[] = $id;
            }
        }
        if ($differs) {
            $pf->warn('level_proficiency_differs_from_rule:' . implode(',', $differs));
        }
        if ($parsed['problems'] || $missing) {
            $suggest = [];
            foreach ($levels as $id => $name) {
                $suggest[] = $id . ',' . level_map::suggest($name, $value);
            }
            $pf->warn('level_proficiency_suggested_csv:' . implode(';', $suggest));
        }
    }

    /**
     * Facts about the catalogue the owner should see before the rehearsal is accepted.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_catalogue(context $ctx, preflight $pf): void {
        if ($ctx->legacy->exists('local_skill_categories')) {
            $n = $ctx->legacy->count('local_skill_categories', ['t.parentid > 0', []]);
            if ($n > 0) {
                $pf->warn('category_parent_ignored:' . $n);
            }
        }
        if ($ctx->legacy->exists('local_skill') && $ctx->legacy->exists('local_skill_categories')) {
            $n = $ctx->legacy->count('local_skill',
                ['t.category NOT IN (SELECT c.id FROM {local_skill_categories} c)', []]);
            if ($n > 0) {
                $pf->warn('skills_without_category:' . $n);
            }
        }
    }

    /**
     * Courses that point at a skill the legacy table does not have (BizLMS never reset course.open_skill when a
     * skill was deleted). They get no link; the count is the report's to show.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_courses(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->exists('local_skill') || !$ctx->legacy->exists('course')
                || !$ctx->legacy->has_column('course', 'open_skill')) {
            return;
        }
        $n = $ctx->legacy->count('course', [
            't.open_skill > 0 AND t.id > 1 AND t.open_skill NOT IN (SELECT s.id FROM {local_skill} s)', [],
        ]);
        $pf->count('courses_with_dangling_skill', $n);
        if ($n > 0) {
            $pf->warn('course_skill_dangling:' . $n);
        }
    }

    /**
     * Interests: the learners with more than one row, and rows whose legacy tenant disagrees with the learner's
     * current one (the column is not copied; this is the cross-check the mapping doc asks for).
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_interests(context $ctx, preflight $pf): void {
        if (!$ctx->legacy->exists('local_interested_skills')) {
            return;
        }
        $groups = $ctx->legacy->count_groups('local_interested_skills', ['usercreated']);
        $rows = $ctx->legacy->count('local_interested_skills');
        if ($rows > $groups) {
            $pf->warn('interest_duplicate_rows:' . ($rows - $groups));
        }
        if (!$ctx->legacy->has_column('local_interested_skills', 'open_costcenterid')) {
            return;
        }
        $mismatch = 0;
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_interested_skills', $after, 2000, ['id', 'usercreated', 'open_costcenterid'],
                ['t.active = 1 AND t.open_costcenterid > 0', []]);
            foreach ($page as $id => $row) {
                $root = $ctx->tenant->root_of_user((int) $row->usercreated);
                if ($root > 0 && $root !== (int) $row->open_costcenterid) {
                    $mismatch++;
                }
                $after = (int) $id;
            }
        } while (count($page) === 2000);
        if ($mismatch > 0) {
            $pf->warn('interest_tenant_mismatch:' . $mismatch);
        }
    }

    /**
     * Archived completions whose learner has no course_completions row at all cannot form a group, so their
     * history is not imported. Count the learners, so the owner sees the size of that gap.
     *
     * @param context $ctx
     * @param preflight $pf
     * @return void
     */
    private function preflight_archive(context $ctx, preflight $pf): void {
        global $DB;
        if (!$ctx->legacy->exists('local_recompletion_cc')) {
            return;
        }
        $n = (int) $DB->get_field_sql(
            'SELECT COUNT(DISTINCT cc.userid) FROM {local_recompletion_cc} cc
              WHERE cc.timecompleted > 0
                AND NOT EXISTS (SELECT 1 FROM {course_completions} x WHERE x.userid = cc.userid)');
        if ($n > 0) {
            $pf->warn('archive_only_learners_not_imported:' . $n);
        }
    }

    /**
     * Every legacy course level: id => name.
     *
     * @param context $ctx
     * @return array<int, string>
     */
    private function all_levels(context $ctx): array {
        $levels = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_course_levels', $after, 1000, ['id', 'name']);
            foreach ($page as $id => $row) {
                $levels[(int) $id] = (string) $row->name;
                $after = (int) $id;
            }
        } while (count($page) === 1000);
        return $levels;
    }
}

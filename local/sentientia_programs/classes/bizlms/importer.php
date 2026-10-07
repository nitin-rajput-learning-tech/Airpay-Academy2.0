<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\file_rehome;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

defined('MOODLE_INTERNAL') || die();

/**
 * The BizLMS program import (ADR-032; mapping doc, section 16).
 *
 * Twelve BizLMS tables land in this plugin's seven:
 *
 *   local_program                 -> local_sentientia_programs            PRESERVE (ids are stored elsewhere)
 *   local_program_levels          -> local_sentientia_programs_levels
 *   local_program_level_courses   -> local_sentientia_programs_courses
 *   local_program_users           -> local_sentientia_programs_users
 *   local_bc_level_completions    -> local_sentientia_programs_lvlcomp    (completed rows)
 *   local_bc_completion_criteria  -> folded into programs and levels
 *   local_bcl_cmplt_criteria      -> folded into levels and courses
 *   local_program_trainers/_trainerfb -> the two conditional tables (expected empty)
 *   local_program_completions_bk, local_bc_level_comp_bk, local_program_test_score -> archived
 *
 * It writes no enrolment, completion, certificate, message, calendar entry or event: a program_completed event
 * would queue evaluations, feed gamification and fire webhooks for something that happened years ago.
 *
 * Every table is optional in sources(): the local copy of production lost all of them but one, and the framework
 * treats a missing required table as a blocker that stops every feature. preflight() restores the real
 * requirement: if local_program is there, so are the tables that hang off it.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract {

    /** Tables that must exist whenever local_program does. */
    private const COMPANIONS = [
        'local_program_levels', 'local_program_level_courses', 'local_program_users',
        'local_bcl_cmplt_criteria', 'local_bc_completion_criteria', 'local_bc_level_completions',
    ];

    /** The tables kept only in the archive. */
    private const ARCHIVE_ONLY = ['local_program_completions_bk', 'local_bc_level_comp_bk', 'local_program_test_score'];

    public function feature(): string {
        return 'program';
    }

    public function component(): string {
        return 'local_sentientia_programs';
    }

    public function requires_version(): int {
        return 2026093001;
    }

    public function depends(): array {
        // Tenant paths are resolved against the organisations the org importer fills.
        return ['org'];
    }

    public function sources(): array {
        $tracking = ['ALL' => 'every course or level', 'AND' => 'every listed course or level',
            'OR' => 'any listed course or level', '' => 'unset, read as ALL'];
        $completion = [0 => 'not completed', 1 => 'completed', '' => 'unset, read as not completed'];
        return [
            // BizLMS set 0 when a program was created and 2 nowhere (its updater had no caller); visible was the switch.
            'local_program' => new source_spec('local_program', false, ['status' => [0 => 'new', 2 => 'completed']]),
            'local_program_levels' => new source_spec('local_program_levels', false),
            'local_program_level_courses' => new source_spec('local_program_level_courses', false),
            'local_bcl_cmplt_criteria' => new source_spec('local_bcl_cmplt_criteria', false,
                ['coursetracking' => $tracking]),
            'local_bc_completion_criteria' => new source_spec('local_bc_completion_criteria', false,
                ['leveltracking' => $tracking]),
            'local_program_users' => new source_spec('local_program_users', false,
                ['completion_status' => $completion]),
            'local_bc_level_completions' => new source_spec('local_bc_level_completions', false,
                ['completion_status' => $completion]),
            'local_program_trainers' => new source_spec('local_program_trainers', false),
            'local_program_trainerfb' => new source_spec('local_program_trainerfb', false),
            'local_program_completions_bk' => new source_spec('local_program_completions_bk', false),
            'local_bc_level_comp_bk' => new source_spec('local_bc_level_comp_bk', false),
            'local_program_test_score' => new source_spec('local_program_test_score', false),
        ];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return [base_step::T_PROGRAMS, base_step::T_LEVELS, base_step::T_COURSES, base_step::T_USERS,
            base_step::T_LVLCOMP, base_step::T_TRAINERS, base_step::T_TRAINERFB];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [base_step::T_PROGRAMS => 'open_path'];
    }

    public function reasons(): array {
        // Retryable is false throughout: the user, level and criteria steps are grouped, and --retry-skipped refuses
        // a grouped step. needsowner is true where a whole program, or learner history nobody can see, is not
        // carried: a program with no name at all, a program the owner chose to skip for want of a tenant, and the
        // backup and test-score tables (decision program.bk_tables = archive_only, mapping doc section 16).
        return [
            new reason('no_name', false, true),
            new reason('tenant_unresolved', false, true),
            new reason('bk_rows_archived', false, true),
            new reason('orphan_user', false, false),
            new reason('orphan_program', false, false),
            new reason('orphan_level', false, false),
            new reason('orphan_course', false, false),
            new reason('orphan_trainer', false, false),
            // The parent is in the source and the import chose not to keep it (detail: the parent's own reason).
            new reason('parent_skipped', false, false),
            // A level criteria row whose programid is not the program its level belongs to: it shaped nothing.
            new reason('criteria_program_mismatch', false, false),
            new reason('invalid_course', false, false),
            new reason('empty_level', false, false),
            new reason('deleted_user', false, false),
            new reason('no_enrolment', false, false),
            new reason('level_not_completed', false, false),
            new reason('criteria_folded', false, false),
            new reason('dup_user_row', false, false),
            new reason('dup_level_course', false, false),
            new reason('dup_level_completion', false, false),
            new reason('dup_criteria', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision('program.completed_without_date', 'Completed in BizLMS but with no completion date', true, null,
                ['completed_flagged', 'not_completed']),
            new decision('program.inactive', 'What a switched-off program becomes', true, null,
                ['archived', 'draft']),
            new decision('program.empty_levels', 'Levels BizLMS created with no course', true, null,
                ['skip', 'import']),
            new decision('program.deleted_users', 'Enrolments of deleted users', true, null, ['import', 'skip']),
            new decision('program.pathless', 'A program with no usable path', true, null,
                ['creator_root', 'cross_tenant_only']),
            new decision('program.bk_tables', 'The backup and test-score tables', true, null, ['archive_only']),
            new decision('tenant.unresolved.program', 'A program whose tenant cannot be worked out', true, null,
                ['pathless', 'skip']),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    public function steps(): array {
        return [
            new program_step(),
            new level_step(),
            new level_course_step(),
            new user_step(),
            new level_completion_step(),
            new criteria_step(false),
            new criteria_step(true),
            new trainer_step(false),
            new trainer_step(true),
            new archive_step('local_program_completions_bk', 'completionsbk', base_step::T_USERS),
            new archive_step('local_bc_level_comp_bk', 'levelcompbk', base_step::T_LVLCOMP),
            new archive_step('local_program_test_score', 'testscore', base_step::T_LVLCOMP),
            new current_level_recompute(),
        ];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();
        $legacy = $ctx->legacy;

        if ($legacy->exists('local_program')) {
            foreach (self::COMPANIONS as $table) {
                if (!$legacy->exists($table)) {
                    $pf->block('missing_table:' . $table);
                }
            }
            $pathless = $legacy->count('local_program', ["t.open_path IS NULL OR t.open_path = ''", []]);
            if ($pathless > 0) {
                $pf->warn('pathless_programs:' . $pathless);
            }
        }
        if ($legacy->exists('local_program_users')) {
            $undated = $legacy->count('local_program_users',
                ['t.completion_status = 1 AND (t.completiondate IS NULL OR t.completiondate <= 0)', []]);
            if ($undated > 0) {
                $pf->warn('completed_without_date:' . $undated);
            }
        }
        foreach (array_merge(self::ARCHIVE_ONLY, ['local_program_trainers', 'local_program_trainerfb']) as $table) {
            if ($legacy->exists($table)) {
                $rows = $legacy->count($table);
                if ($rows > 0) {
                    $pf->warn(($table === 'local_program_trainers' || $table === 'local_program_trainerfb'
                        ? 'trainer_rows_imported:' : 'bk_rows_archived:') . $table . ':' . $rows);
                }
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
        $imported = "m.targettable = :t AND m.outcome IN ('imported', 'adopted')";
        $map = legacymap::TABLE;

        // Once the site is open (the runbook sets bizlms_production_open) administrators may reorder levels and
        // remove a level or an enrolment that carries no history, so the checks that compare imported rows with
        // each other stop there, like the framework's own map-to-target check (ADR-032, "Parity hooks" 2).
        if ((int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0) {
            return [];
        }

        // Levels of an imported program sit at 0, 1, 2 ... with no gap and no repeat.
        $rows = $DB->get_records_sql(
            "SELECT l.programid, COUNT(1) AS n, MIN(l.sortorder) AS lo, MAX(l.sortorder) AS hi,
                    COUNT(DISTINCT l.sortorder) AS d
               FROM {" . base_step::T_LEVELS . "} l
               JOIN {" . $map . "} m ON m.targetid = l.id AND {$imported}
           GROUP BY l.programid", ['t' => base_step::T_LEVELS]);
        $gaps = 0;
        foreach ($rows as $row) {
            if ((int) $row->lo !== 0 || (int) $row->hi !== (int) $row->n - 1 || (int) $row->d !== (int) $row->n) {
                $gaps++;
            }
        }
        if ($gaps > 0) {
            $failures[] = 'levels_sortorder_not_dense:' . $gaps;
        }

        // A learner's current level belongs to the learner's program.
        $wrong = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . base_step::T_USERS . "} u
               JOIN {" . $map . "} m ON m.targetid = u.id AND {$imported}
          LEFT JOIN {" . base_step::T_LEVELS . "} l ON l.id = u.currentlevelid
              WHERE u.currentlevelid IS NOT NULL AND (l.id IS NULL OR l.programid <> u.programid)",
            ['t' => base_step::T_USERS]);
        if ($wrong > 0) {
            $failures[] = 'current_level_outside_program:' . $wrong;
        }

        $badstatus = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . base_step::T_USERS . "} u
               JOIN {" . $map . "} m ON m.targetid = u.id AND {$imported}
              WHERE u.status NOT IN (0, 1, 2)", ['t' => base_step::T_USERS]);
        if ($badstatus > 0) {
            $failures[] = 'enrolment_status_out_of_range:' . $badstatus;
        }

        // A stored level completion needs the learner's enrolment and a level of the same program.
        $orphans = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . base_step::T_LVLCOMP . "} c
               JOIN {" . $map . "} m ON m.targetid = c.id AND {$imported}
          LEFT JOIN {" . base_step::T_USERS . "} u ON u.programid = c.programid AND u.userid = c.userid
              WHERE u.id IS NULL", ['t' => base_step::T_LVLCOMP]);
        if ($orphans > 0) {
            $failures[] = 'level_completion_without_enrolment:' . $orphans;
        }
        $crossed = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . base_step::T_LVLCOMP . "} c
               JOIN {" . $map . "} m ON m.targetid = c.id AND {$imported}
               JOIN {" . base_step::T_LEVELS . "} l ON l.id = c.levelid
              WHERE l.programid <> c.programid", ['t' => base_step::T_LVLCOMP]);
        if ($crossed > 0) {
            $failures[] = 'level_completion_in_another_program:' . $crossed;
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        if ($ctx->dryrun) {
            return;
        }
        $this->rehome_logos($ctx);
    }

    /**
     * Copy each program's logo to the file area the Sentientia pluginfile callback serves.
     *
     * BizLMS saved the logo under a category context with the draft item id it kept in local_program.programlogo
     * (program.php:70). Sentientia serves it from the system context with the program id as item id. The legacy
     * files stay where they are; a file that is already there is left alone, so this runs twice without harm.
     *
     * @param context $ctx
     * @return void
     */
    private function rehome_logos(context $ctx): void {
        global $DB;
        $legacy = $ctx->legacy;
        if (!$legacy->exists('local_program') || !$legacy->has_column('local_program', 'programlogo')) {
            return;
        }
        $systemcontext = \context_system::instance()->id;
        $after = 0;
        do {
            $page = $legacy->page('local_program', $after, 500, ['programlogo'], ['t.programlogo > 0', []]);
            foreach ($page as $legacyid => $row) {
                $after = (int) $legacyid;
                $programid = $ctx->map->resolve('local_program', (int) $legacyid);
                if ($programid === null) {
                    continue;
                }
                $contexts = $DB->get_fieldset_sql(
                    "SELECT DISTINCT contextid FROM {files}
                      WHERE component = :component AND filearea = :filearea AND itemid = :itemid AND filename <> '.'",
                    ['component' => 'local_program', 'filearea' => 'programlogo', 'itemid' => (int) $row->programlogo]);
                foreach ($contexts as $contextid) {
                    file_rehome::copy_area(
                        ['contextid' => (int) $contextid, 'component' => 'local_program', 'filearea' => 'programlogo',
                            'itemid' => (int) $row->programlogo],
                        ['contextid' => $systemcontext, 'component' => 'local_sentientia_programs',
                            'filearea' => 'programlogo', 'itemid' => $programid]);
                }
            }
        } while (count($page) === 500);
    }
}

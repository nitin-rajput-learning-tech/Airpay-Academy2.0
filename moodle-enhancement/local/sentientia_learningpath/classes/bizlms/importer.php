<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\file_rehome;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032 importer for the BizLMS learning plans (mapping doc, section 17).
 *
 * BizLMS local_learningplan, _courses, _user and local_plan_course_status become the
 * Sentientia learning paths, their courses, their learner rows and (if the table has rows)
 * the per-course status rows. The legacy tables are read only and stay as the archive.
 *
 * - Paths keep their BizLMS ids (PRESERVE): certificates, rating rows and requests that
 *   BizLMS wrote name a plan by id and are not rewritten.
 * - Courses and learner rows get new ids (MAP) and find their path through the map.
 * - Nothing here enrols anybody, completes anything, sends anything or turns the
 *   adaptive journey engine on. Enrolment rows are copied as data.
 *
 * Runs after org (tenant paths are checked against the organisation table) and skills
 * (open_skill and open_level are resolved through their map).
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract {

    /** Feature key. */
    public const FEATURE = 'learningplan';

    /** Legacy tables. */
    public const SRC_PLAN = 'local_learningplan';
    public const SRC_COURSE = 'local_learningplan_courses';
    public const SRC_USER = 'local_learningplan_user';
    public const SRC_STATUS = 'local_plan_course_status';

    /** Target tables. */
    public const PATHS = 'local_sentientia_learningpath';
    public const COURSES = 'local_sentientia_learningpath_courses';
    public const USERS = 'local_sentientia_learningpath_users';
    public const STATUS = 'local_sentientia_lp_course_status';

    /** Plugin version that carries the columns and the table the steps write to (upgrade step 2026093001). */
    public const REQUIRES_VERSION = 2026093001;

    /** File area the plan cover image lives in, before and after the import. */
    public const FILEAREA = 'summaryfile';

    /** Rows read per page when finalise() walks the plans that have a cover file. */
    private const FILE_PAGE = 500;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_learningpath';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * Run order from the mapping doc, section 2: org (tenant paths), skills (open_skill and open_level).
     *
     * @return string[]
     */
    public function depends(): array {
        return ['org', 'skills'];
    }

    /**
     * Columns the BizLMS install file lists but production may not have. Two families: columns added by
     * later upgrade steps (or by none: approvalreqd, startdate, enddate and objective are in no upgrade
     * step of the snapshot), and the production-only columns the mapping doc names (costcenter,
     * certificateid). Everything not listed here is read as required.
     */
    private const PLAN_OPTIONAL = [
        'shortname', 'description', 'objective', 'startdate', 'enddate', 'approvalreqd', 'selfenrol', 'lpsequence',
        'learning_type', 'open_points', 'open_categoryid', 'open_skill', 'open_level', 'usercreated', 'usermodified',
        'summaryfile', 'costcenter', 'certificateid',
    ];

    /**
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            self::SRC_PLAN => new source_spec(self::SRC_PLAN, true, [
                // BizLMS showed a plan to learners only when visible = 1 (userdashboard_content.php:20,47-48).
                'visible' => ['1' => 'shown, imported as an active path', '0' => 'hidden, imported as archived',
                    '' => 'NULL, treated as hidden'],
            ], self::PLAN_OPTIONAL),
            self::SRC_COURSE => new source_spec(self::SRC_COURSE, true, [
                // The writer that set a module type is commented out in BizLMS: only a course is ever listed.
                'moduletype' => ['' => 'no module type, a course', 'course' => 'a course'],
                // 'and' is the course the learner must finish, 'or' and NULL are optional (lib.php:1006-1010).
                // Some production rows carry upper case; the comparison is byte-exact, so any other spelling
                // blocks until the owner maps it in the decisions file (ADR-032, R8).
                'nextsetoperator' => ['' => 'NULL or empty, optional', 'and' => 'must finish', 'AND' => 'must finish',
                    'or' => 'optional', 'OR' => 'optional'],
            ], ['moduletype', 'instance', 'nextsetoperator', 'timemodified', 'usercreated', 'usermodified']),
            self::SRC_USER => new source_spec(self::SRC_USER, true, [
                // BizLMS only ever writes NULL (not completed) and 1 (completed): lib.php:780-786.
                'status' => ['' => 'NULL, not completed', '0' => 'not completed', '1' => 'completed'],
            ], ['startdate', 'timemodified', 'usercreated', 'usermodified']),
            // Nothing in BizLMS writes or reads this table; the mapping doc imports it only if it has rows.
            self::SRC_STATUS => new source_spec(self::SRC_STATUS, false, [], ['usercreated', 'usermodified']),
        ];
    }

    /**
     * local_learningplan_approval is the request feature's table (mapping doc, section 19).
     *
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [self::PATHS, self::COURSES, self::USERS, self::STATUS];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * Only the path carries a tenant; its courses and learner rows belong to it through pathid.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [self::PATHS => 'open_path'];
    }

    /**
     * Every skipped row is a loss of history the owner has to accept after the rehearsal, so the
     * skip reasons need the owner. A merged duplicate loses nothing: the winner carries the data.
     *
     * @return reason[]
     */
    public function reasons(): array {
        return [
            new reason('no_name', false, true),
            new reason('tenant_unresolved', false, true),
            new reason('orphan_plan', false, true),
            new reason('no_course', false, true),
            new reason('orphan_course', false, true),
            new reason('orphan_user', false, true),
            new reason('dup_course', false, false),
            new reason('dup_enrolment', false, false),
            new reason('dup_course_status', false, false),
        ];
    }

    /**
     * The owner choices this importer acts on. Each one that has a single value the code implements
     * lists only that value, so a change in the signed file blocks the feature instead of being ignored.
     *
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision('tenant.unresolved.learningplan',
                'A plan whose tenant cannot be resolved: import with no path (visible to cross-tenant callers only) or skip it',
                true, null, ['pathless', 'skip']),
            new decision('learningplan.not_completed',
                'A learner who has not completed: In progress when a course of the path is completed, else Enrolled',
                true, null, ['derive_in_progress', 'enrolled']),
            new decision('learningplan.enforce_rules',
                'approvalreqd, selfenrol and sequential are stored, nothing enforces them',
                true, null, ['store_only']),
            new decision('learningplan.dates_as',
                'BizLMS start and end dates become the path enrolment window',
                true, null, ['enrolment_window']),
            new decision('learningplan.history_on_archived_paths',
                'Completed history on archived paths is for admins only (the learner page hides it)',
                true, null, ['hidden_from_learners']),
            new decision('learningplan.tenant_fallback_order',
                'Tenant of a plan with an empty path: cost centre column, root shared by the enrolled users, creator root, none',
                true, null, [['costcenter_column_when_valid', 'root_shared_by_all_enrolled_users', 'creator_root',
                    'null_path_reported']]),
        ];
    }

    /**
     * Paths, their courses and their learners land together, so no reader ever sees a path without its rows.
     *
     * @return bool
     */
    public function atomic(): bool {
        return true;
    }

    /**
     * @return array<\local_sentientia_platform\bizlms\step|\local_sentientia_platform\bizlms\recompute_step>
     */
    public function steps(): array {
        return [new path_step(), new course_step(), new user_step(), new course_status_step()];
    }

    /**
     * Read-only. Warnings only: a blocker here would be a fact the framework already checks (enums, columns,
     * PRESERVE collisions).
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        $pf = new preflight();
        $legacy = $ctx->legacy;

        // The BizLMS learner row has a start date that no BizLMS code ever wrote (mapping doc, section 17:
        // startdate -> timestarted "NEW only if production has non-NULL values"). The target has no such
        // column, so a value here is learner history this import would drop. That is not a warning to scroll
        // past: it blocks until the column and its copy are built, or the owner decides the values can stay
        // in the legacy table (a change to this check). The April copy has none.
        if ($legacy->exists(self::SRC_USER) && $legacy->has_column(self::SRC_USER, 'startdate')) {
            $n = $legacy->count(self::SRC_USER, ['t.startdate > 0', []]);
            if ($n > 0) {
                $pf->block('user_startdate_has_no_target_column:' . $n);
            }
        }
        // A plan with no path goes through the tenant fallback order; say how many that is.
        if ($legacy->exists(self::SRC_PLAN)) {
            $n = $legacy->count(self::SRC_PLAN, ["(t.open_path IS NULL OR t.open_path = '')", []]);
            if ($n > 0) {
                $pf->warn('plans_with_empty_open_path:' . $n);
            }
            if ($legacy->has_column(self::SRC_PLAN, 'summaryfile')) {
                $pf->count('plans_with_cover_file', $legacy->count(self::SRC_PLAN, ['t.summaryfile > 0', []]));
            }
        }
        if (!$legacy->exists(self::SRC_STATUS)) {
            $pf->warn('local_plan_course_status_absent');
        }
        return $pf;
    }

    /**
     * Facts that hold right after the import and stay true after go-live: no child row without a
     * path, no learner row without a user, only known status values and mandatory flags.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $map = '{' . legacymap::TABLE . '}';
        $imported = "m.targettable = :mt AND m.targetid = t.id AND m.outcome IN ('imported', 'adopted')";

        foreach ([self::COURSES, self::USERS, self::STATUS] as $table) {
            $orphans = (int) $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {" . $table . "} t
                   JOIN {$map} m ON {$imported}
              LEFT JOIN {" . self::PATHS . "} p ON p.id = t.pathid
                  WHERE p.id IS NULL", ['mt' => $table]);
            if ($orphans > 0) {
                $failures[] = 'child_row_without_a_path:' . $table . ':' . $orphans;
            }
        }
        foreach ([self::USERS, self::STATUS] as $table) {
            $nouser = (int) $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {" . $table . "} t
                   JOIN {$map} m ON {$imported}
              LEFT JOIN {user} u ON u.id = t.userid
                  WHERE u.id IS NULL", ['mt' => $table]);
            if ($nouser > 0) {
                $failures[] = 'learner_row_without_a_user:' . $table . ':' . $nouser;
            }
        }
        $badstatus = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::USERS . "} t
               JOIN {$map} m ON {$imported}
              WHERE t.status NOT IN (0, 1, 2)", ['mt' => self::USERS]);
        if ($badstatus > 0) {
            $failures[] = 'unknown_learner_status:' . $badstatus;
        }
        $badflag = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::COURSES . "} t
               JOIN {$map} m ON {$imported}
              WHERE t.mandatory NOT IN (0, 1)", ['mt' => self::COURSES]);
        if ($badflag > 0) {
            $failures[] = 'unknown_mandatory_flag:' . $badflag;
        }
        return $failures;
    }

    /**
     * Copy each plan's cover image to the Sentientia file area (item id = the path id, system context).
     *
     * BizLMS stored the file under the draft item id it kept in local_learningplan.summaryfile, not under
     * the plan id (classes/lib/lib.php:38,128), in whichever context its accesslib chose. The source files
     * stay where they are; a file already at the target is left alone, so a second run copies nothing.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
        global $DB;
        $legacy = $ctx->legacy;
        if (!$legacy->exists(self::SRC_PLAN) || !$legacy->has_column(self::SRC_PLAN, 'summaryfile')) {
            return;
        }
        $systemid = (int) \context_system::instance()->id;
        $after = 0;
        do {
            $page = $legacy->page(self::SRC_PLAN, $after, self::FILE_PAGE, ['summaryfile'], ['t.summaryfile > 0', []]);
            if (!$page) {
                break;
            }
            $after = (int) max(array_keys($page));
            $paths = $ctx->map->resolve_many(self::SRC_PLAN, array_keys($page));
            foreach ($page as $planid => $row) {
                $pathid = (int) ($paths[$planid] ?? 0);
                $itemid = (int) $row->summaryfile;
                if ($pathid <= 0 || $itemid <= 0) {
                    continue;
                }
                $contexts = $DB->get_fieldset_sql(
                    "SELECT DISTINCT contextid
                       FROM {files}
                      WHERE component = :component AND filearea = :filearea AND itemid = :itemid",
                    ['component' => 'local_learningplan', 'filearea' => self::FILEAREA, 'itemid' => $itemid]);
                foreach ($contexts as $contextid) {
                    file_rehome::copy_area(
                        ['contextid' => (int) $contextid, 'component' => 'local_learningplan',
                            'filearea' => self::FILEAREA, 'itemid' => $itemid],
                        ['contextid' => $systemid, 'component' => 'local_sentientia_learningpath',
                            'filearea' => self::FILEAREA, 'itemid' => $pathid]);
                }
            }
        } while (count($page) === self::FILE_PAGE);
    }
}

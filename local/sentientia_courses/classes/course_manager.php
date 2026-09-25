<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Course manager — progress tracking, enrollment helpers, course queries.
 *
 * Replaces \local_courses\lib\accesslib methods and the scattered
 * course queries found throughout core_renderer and airpay plugins.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_manager {

    /**
     * Get course completion percentage for a user.
     *
     * Drop-in replacement for:
     *   \local_courses\lib\accesslib()::get_user_course_progress_percentage($courseid, $userid)
     *
     * Uses Moodle core completion API (no BizLMS wrapper needed).
     *
     * @param int $courseid
     * @param int $userid
     * @return float  Percentage 0-100, or 0 if no completion tracking
     */
    public static function get_progress_percentage(int $courseid, int $userid): float {
        $course = get_course($courseid);
        if (empty($course) || $course->enablecompletion == 0) {
            return 0.0;
        }

        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return $progress !== null ? round((float) $progress, 1) : 0.0;
    }

    /**
     * Check if a user has completed a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_completed(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('course_completions', [
            'course'        => $courseid,
            'userid'        => $userid,
            'timecompleted' => ['>', 0],
        ]);
    }

    /**
     * Get completion deadline for a course based on open_coursecompletiondays.
     *
     * @param int $courseid
     * @param int $userid
     * @return int|null  Unix timestamp of deadline, or null if no deadline
     */
    public static function get_completion_deadline(int $courseid, int $userid): ?int {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, open_coursecompletiondays');
        if (empty($course->open_coursecompletiondays) || $course->open_coursecompletiondays <= 0) {
            return null;
        }

        // Get enrollment start time.
        $enroltime = $DB->get_field_sql(
            "SELECT MIN(ue.timestart)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid AND ue.timestart > 0",
            ['cid' => $courseid, 'uid' => $userid]
        );

        if (empty($enroltime)) {
            return null;
        }

        return (int) $enroltime + ($course->open_coursecompletiondays * 86400);
    }

    /**
     * Count visible courses for a tenant.
     *
     * @param string $pathfilter  a tenant/org PATH such as '/1' or '/1/2' (NOT a LIKE
     *                            pattern); matched exact-or-descendant. Empty = all.
     * @return int
     */
    public static function count_visible_courses(string $pathfilter = ''): int {
        global $DB;

        $sql = "SELECT COUNT(id) FROM {course} WHERE visible = 1 AND id != 1";
        $params = [];

        if (!empty($pathfilter)) {
            [$psql, $pargs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $pathfilter, '', 'open_path', 'cvc');
            $sql .= " AND {$psql}";
            $params += $pargs;
        }

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Tenant scope fragment for the Manage Courses admin page.
     *
     * Returns exactly the scope external\list_courses applies to its rows
     * (the datatable), so any KPI tile or filter that reuses it can never
     * contradict the table's "N of N" footer:
     *   - site admins   → no filter (1=1), i.e. every course;
     *   - tenant admins → their own open_path tree, PLUS legacy
     *                     NULL-open_path courses, which stay visible until
     *                     the data migration completes. See
     *                     external\list_courses (path_filter allow_null),
     *                     list_courses_test::test_null_open_path_courses_remain_visible,
     *                     and the 2 production rows CTI002 / BC001_1.
     *
     * @param string $alias Table alias for {course} (e.g. 'c'); '' = none.
     * @return array{0:string, 1:array}  [$sqlfragment, $params]
     */
    public static function manage_scope_sql(string $alias = ''): array {
        return \local_sentientia_platform\tenant::path_filter($alias, 'open_path', true);
    }

    /**
     * KPI tile counts for the Manage Courses page, scoped to the SAME row
     * set the datatable lists.
     *
     * Fixes the UAT finding where a tenant admin saw a global "15 Total"
     * above a tenant-scoped "1-5 of 5" table. Site admins keep the global
     * count (manage_scope_sql returns 1=1 for them).
     *
     * @return array{total:int, visible:int, hidden:int}
     */
    public static function manage_kpi_counts(): array {
        global $DB;

        [$scopesql, $params] = self::manage_scope_sql('');
        $base = "id > 1 AND {$scopesql}";

        $total   = (int) $DB->count_records_select('course', $base, $params);
        $visible = (int) $DB->count_records_select('course', "{$base} AND visible = 1", $params);

        return [
            'total'   => $total,
            'visible' => $visible,
            'hidden'  => max(0, $total - $visible),
        ];
    }

    /**
     * Category-filter options for the Manage Courses page.
     *
     * Site admins keep every category (unchanged behaviour). Tenant admins
     * get only categories that hold at least one course in their own row
     * set, so one tenant's admin never sees another tenant's category names
     * in the dropdown (UAT ZEEA finding). Shape matches the manage template:
     * [{id, name}] with depth-indented names.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public static function manage_category_options(): array {
        global $DB;

        // ADR-031: only a cross-tenant caller (site admin or :crosstenant) sees every category.
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $categories = $DB->get_records('course_categories', null, 'sortorder ASC',
                'id, name, depth');
        } else {
            [$scopesql, $params] = self::manage_scope_sql('c');
            $categories = $DB->get_records_sql(
                "SELECT DISTINCT cat.id, cat.name, cat.depth, cat.sortorder
                   FROM {course_categories} cat
                   JOIN {course} c ON c.category = cat.id
                  WHERE c.id > 1 AND {$scopesql}
               ORDER BY cat.sortorder ASC",
                $params);
        }

        $options = [];
        foreach ($categories as $cat) {
            $options[] = [
                'id'   => (int) $cat->id,
                'name' => str_repeat('— ', max(0, ((int) $cat->depth) - 1))
                        . format_string($cat->name),
            ];
        }
        return $options;
    }

    /**
     * Category options for the create / edit course form.
     *
     * ADR-031 (follow-up, 2026-09-25): a cross-tenant caller keeps every
     * visible category, as before. For anyone else a category is listed
     * unless every course in it belongs to another tenant, because such a
     * category name is that tenant's data (the rule manage_category_options()
     * already applies to the Manage Courses filter - UAT ZEEA #4). So a scoped
     * caller sees:
     *   - categories holding at least one course in their manage scope (their
     *     own tenant's courses, plus legacy no-open_path ones) - which always
     *     includes the category of any course they can edit;
     *   - empty categories, which carry no tenant's data.
     * If that leaves nothing (every category holds another tenant's courses),
     * the site default category is offered, so a new tenant can still create
     * a course.
     *
     * @return array<int, string> category id => depth-indented name
     */
    public static function edit_category_options(): array {
        global $DB;

        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $cats = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC',
                'id, name, depth, sortorder');
        } else {
            [$scopesql, $params] = self::manage_scope_sql('c');
            $cats = $DB->get_records_sql(
                "SELECT cat.id, cat.name, cat.depth, cat.sortorder
                   FROM {course_categories} cat
                  WHERE cat.visible = 1
                    AND (NOT EXISTS (SELECT 1 FROM {course} c0
                                      WHERE c0.category = cat.id AND c0.id > 1)
                         OR EXISTS (SELECT 1 FROM {course} c
                                     WHERE c.category = cat.id AND c.id > 1 AND {$scopesql}))
               ORDER BY cat.sortorder ASC",
                $params);
            if (!$cats) {
                $default = \core_course_category::get_default();
                $cats = [(int) $default->id => (object) ['id' => (int) $default->id,
                    'name' => $default->name, 'depth' => (int) $default->depth]];
            }
        }

        $options = [];
        foreach ($cats as $c) {
            $options[(int) $c->id] = str_repeat('— ', max(0, ((int) $c->depth) - 1))
                . format_string($c->name);
        }
        return $options;
    }

    /**
     * Check if user has course management capability (L&D admin detection).
     *
     * Checks BOTH old (local/courses:manage) and new (local/sentientia_courses:manage)
     * capabilities during transition.
     *
     * @param \context|null $context  (null = system context)
     * @return bool
     */
    public static function can_manage(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();

        return is_siteadmin()
            || has_capability('local/sentientia_courses:manage', $context)
            || has_capability('local/courses:manage', $context);
    }

    /**
     * Check if user can enrol others.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_enrol(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();

        return is_siteadmin()
            || has_capability('local/sentientia_courses:enrol', $context)
            || has_capability('local/courses:enrol', $context);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADR-031 tenant scope (2026-09-25)
    //
    // A plugin capability says WHAT a caller may do, never WHERE. Every
    // :create / :update / :visibility / :delete / :enrol holder is a tenant
    // admin (a manager-archetype role at system context) unless
    // tenant::is_cross_tenant() says otherwise, so every write that names a
    // course or a user checks the TARGET against the caller's tenant here.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Is a course path inside tenant $root's tree ('/N' or '/N/...')?
     *
     * @param string $path course.open_path (may be empty)
     * @param int $root tenant root
     * @return bool
     */
    public static function path_in_tenant(string $path, int $root): bool {
        $path = rtrim(trim($path), '/');
        if ($root <= 0 || $path === '') {
            return false;
        }
        $exact = '/' . $root;
        return $path === $exact || strpos($path, $exact . '/') === 0;
    }

    /**
     * ADR-031: refuse a WRITE to a course outside the caller's tenant.
     *
     * Stricter than tenant::require_path_access(), which lets an empty
     * open_path through for reads. A legacy course with no open_path is
     * listed for every tenant, so hiding, editing, re-homing or deleting it
     * reaches every tenant: that is left to cross-tenant callers.
     *
     * @param \stdClass $course a course record (open_path may be missing or null)
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_course_write_access(\stdClass $course): void {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $path = rtrim(trim((string) ($course->open_path ?? '')), '/');
        if ($path === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path);
    }

    /**
     * ADR-031: the open_path a create/update may write for an organisation id.
     *
     * Cross-tenant callers keep the old behaviour (an unknown org id is
     * ignored). A scoped caller may only pick an org inside their own tenant:
     * until 2026-09-25 any tenant admin could move a course into, or plant one
     * inside, another tenant by choosing that tenant's org.
     *
     * @param int $orgid local_sentientia_org.id (> 0)
     * @return string|null|false the org's path exactly as stored (null only
     *         for a cross-tenant caller's org with no path), or false when the
     *         org does not exist (cross-tenant only: ignored, as before)
     * @throws \moodle_exception error_outoftenant
     */
    public static function org_path_for_write(int $orgid) {
        global $DB;
        $org = $DB->get_record('local_sentientia_org', ['id' => $orgid], 'id, path');
        $crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();
        if (!$org) {
            if ($crosstenant) {
                return false;
            }
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if (!$crosstenant) {
            $path = rtrim(trim((string) $org->path), '/');
            if ($path === '') {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
            \local_sentientia_platform\tenant::require_path_access($path);
        }
        return $org->path;
    }

    /**
     * Organisation options for the create / edit course form.
     *
     * ADR-031: a cross-tenant caller keeps every org. A tenant admin gets
     * only their own tenant's orgs - the dropdown used to list every
     * tenant's org structure. A scoped caller with no tenant gets no org
     * (path_filter is 1=0).
     *
     * Everyone keeps "No specific organisation" (0): on edit it leaves
     * open_path unchanged, so a course whose path matches no org row is not
     * silently re-homed to the first option; on create, create() gives a
     * scoped caller's course their own tenant root instead of the
     * no-open_path course every tenant's list used to show.
     *
     * @return array<int, string> org id => indented name
     */
    public static function org_options(): array {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $orgs = $DB->get_records('local_sentientia_org', ['visible' => 1],
                'depth ASC, fullname ASC', 'id, fullname, depth');
        } else {
            [$tsql, $targs] = \local_sentientia_platform\tenant::path_filter('', 'path');
            $orgs = $DB->get_records_select('local_sentientia_org', "visible = 1 AND {$tsql}",
                $targs, 'depth ASC, fullname ASC', 'id, fullname, depth');
        }
        $options = [0 => '— No specific organisation —'];
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, (int) $o->depth - 1));
            $options[(int) $o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }

    /**
     * ADR-031: the caller's enrolment scope.
     *
     * @param int|null $actorid defaults to the current user
     * @return int|null null = cross-tenant (no restriction), otherwise the tenant root
     * @throws \moodle_exception invalidtenant for a scoped caller whose tenant does not resolve
     */
    public static function enrol_scope_root(?int $actorid = null): ?int {
        global $DB, $USER;
        $actorid = $actorid ?? (int) ($USER->id ?? 0);
        if (\local_sentientia_platform\tenant::is_cross_tenant($actorid)) {
            return null;
        }
        $actor = ($actorid === (int) ($USER->id ?? 0)) ? $USER
            : $DB->get_record('user', ['id' => $actorid], 'id, open_path');
        $root = $actor ? \local_sentientia_platform\tenant::root_for_user($actor) : 0;
        if ($root <= 0) {
            throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
        }
        return $root;
    }

    /**
     * ADR-031: may a caller scoped to $root manage enrolments in this course?
     *
     * True when the course is in the tenant's tree, has been shared to the
     * tenant (Sprint C sharing), or is a legacy course with no open_path (the
     * Manage Courses list shows those to every tenant; the users enrolled or
     * unenrolled are still checked separately against the tenant).
     *
     * @param \stdClass $course record carrying id and open_path
     * @param int $root tenant root (> 0)
     * @return bool
     */
    public static function course_in_enrol_scope(\stdClass $course, int $root): bool {
        $path = rtrim(trim((string) ($course->open_path ?? '')), '/');
        if ($path === '' || self::path_in_tenant($path, $root)) {
            return true;
        }
        return sharing_manager::is_course_shared_to((int) $course->id, $root);
    }

    /**
     * ADR-031: refuse an enrol / unenrol unless the course and every target
     * user are in the caller's tenant. Cross-tenant callers pass.
     *
     * @param int $courseid
     * @param int[] $userids target users
     * @param int|null $actorid defaults to the current user
     * @return int|null the caller's tenant root, or null when cross-tenant
     * @throws \moodle_exception invalidtenant / error_outoftenant
     */
    public static function require_enrol_scope(int $courseid, array $userids = [],
                                               ?int $actorid = null): ?int {
        global $DB;
        $root = self::enrol_scope_root($actorid);
        if ($root === null) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        if (!$course || !self::course_in_enrol_scope($course, $root)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        foreach ($userids as $uid) {
            \local_sentientia_platform\tenant::require_same_tenant_user((int) $uid, $actorid);
        }
        return $root;
    }

    /**
     * ADR-031 (follow-up): look up users by email inside an enrolment scope.
     *
     * With allowaccountssameemail on, one address can belong to accounts in
     * two tenants. get_record('user', ['email' => ...]) then returned whichever
     * row came first - possibly the foreign one, so the in-tenant user read as
     * "not found" (and core raised a debugging notice). The lookup is now
     * bounded to the caller's tenant before any row is picked.
     *
     * At most two rows are returned, oldest first, so a caller can tell "one
     * match" from "ambiguous" without loading every duplicate.
     *
     * @param string $email exact address, as the CSV gives it
     * @param int|null $root the caller's tenant root; null = cross-tenant (every tenant)
     * @param string $fields user columns to fetch; must include id
     * @return \stdClass[] 0, 1 or 2 non-deleted users
     */
    public static function users_by_email_in_scope(string $email, ?int $root,
                                                   string $fields = 'id, open_path, suspended'): array {
        global $DB;
        $email = trim($email);
        if ($email === '' || ($root !== null && $root <= 0)) {
            return [];
        }
        [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
            $root === null ? '' : '/' . $root, '', 'open_path', 'uemscope');
        return array_values($DB->get_records_select('user',
            "deleted = 0 AND email = :uemail AND {$tsql}",
            ['uemail' => $email] + $targs, 'id ASC', $fields, 0, 2));
    }

    /**
     * Role shortnames the enrol picker never offers and the enrol CSV never
     * accepts, for any caller. 'administrator' is the BizLMS tenant-admin
     * role (manager archetype, UAT role 9).
     */
    public const ENROL_HIDDEN_ROLE_SHORTNAMES = ['guest', 'frontpage', 'user', 'administrator'];

    /**
     * ADR-031 decision 6: archetypes a scoped caller may never give.
     */
    private const SCOPED_FORBIDDEN_ARCHETYPES = ['manager', 'coursecreator', 'guest', 'user', 'frontpage'];

    /**
     * ADR-031 decision 6: shortnames a scoped caller may never give (whatever
     * archetype a site has recorded for them).
     */
    private const SCOPED_FORBIDDEN_SHORTNAMES = ['manager', 'coursecreator', 'administrator',
        'guest', 'user', 'frontpage'];

    /**
     * Site-administration capabilities. A role whose definition allows any of
     * these is a site-level role, and a scoped caller may never give it, even
     * in a course context: holding it through an enrolment would reach beyond
     * the course. Course-level roles (editingteacher, teacher, student, the
     * BizLMS employee role) hold none of them.
     */
    public const SITE_LEVEL_CAPABILITIES = [
        'moodle/site:config',
        'moodle/role:manage',
        'moodle/role:override',
        'moodle/user:create',
        'moodle/user:update',
        'moodle/user:delete',
        'moodle/course:create',
        'moodle/category:manage',
        'local/sentientia_platform:crosstenant',
    ];

    /**
     * Roles a scoped caller may never give by enrolment: the manager and
     * coursecreator archetypes (UAT's tenant-admin role 9 is one), the core
     * non-course roles, and any site-level role (SITE_LEVEL_CAPABILITIES).
     *
     * @return int[] role ids
     */
    public static function scoped_forbidden_role_ids(): array {
        global $DB;
        $ids = [];
        foreach ($DB->get_records('role', null, 'sortorder ASC', 'id, shortname, archetype') as $r) {
            if (in_array((string) $r->archetype, self::SCOPED_FORBIDDEN_ARCHETYPES, true)
                    || in_array((string) $r->shortname, self::SCOPED_FORBIDDEN_SHORTNAMES, true)) {
                $ids[(int) $r->id] = (int) $r->id;
            }
        }
        [$capsql, $capparams] = $DB->get_in_or_equal(self::SITE_LEVEL_CAPABILITIES, SQL_PARAMS_NAMED, 'slcap');
        $sitelevel = $DB->get_fieldset_sql(
            "SELECT DISTINCT rc.roleid
               FROM {role_capabilities} rc
              WHERE rc.contextid = :sysctx AND rc.permission = :allow AND rc.capability {$capsql}",
            ['sysctx' => \context_system::instance()->id, 'allow' => CAP_ALLOW] + $capparams);
        foreach ($sitelevel as $rid) {
            $ids[(int) $rid] = (int) $rid;
        }
        return array_values($ids);
    }

    /**
     * Learner roles: archetype student, plus the BizLMS 'employee' role.
     *
     * @return int[] role ids
     */
    public static function learner_role_ids(): array {
        global $DB;
        $ids = [];
        foreach ($DB->get_records('role', null, 'sortorder ASC', 'id, shortname, archetype') as $r) {
            if ($r->archetype === 'student' || in_array($r->shortname, ['student', 'employee'], true)) {
                $ids[] = (int) $r->id;
            }
        }
        return $ids;
    }

    /**
     * ADR-031: which course roles may this enrolment grant?
     *
     * null = no extra restriction: a cross-tenant caller (the picker and the
     * CSV still drop ENROL_HIDDEN_ROLE_SHORTNAMES for everyone).
     *
     * A scoped caller (decision 6, follow-up 2026-09-25): only roles Moodle's
     * allow-assign matrix lets them assign in THIS course's context
     * (get_assignable_roles(), the check core's own enrolment UI makes and
     * enrol_user() does not), minus scoped_forbidden_role_ids() - never
     * manager, coursecreator, the tenant-admin role or any site-level role.
     * Until this date a tenant admin could enrol anyone in their tenant as
     * manager or coursecreator in their own tenant's courses, and the CSV took
     * any role shortname, 'administrator' included.
     *
     * In a course their tenant does not own - shared in from another tenant,
     * or a legacy course with no open_path - learner roles only on top of
     * that: a teacher role there would let the enroller's people edit a course
     * another tenant owns.
     *
     * @param \stdClass $course record carrying id and open_path
     * @param int|null $root the caller's tenant root; null = cross-tenant
     * @param int|null $actorid the enrolling user; defaults to the current user
     * @return int[]|null
     */
    public static function enrol_allowed_role_ids(\stdClass $course, ?int $root,
                                                  ?int $actorid = null): ?array {
        global $USER;
        if ($root === null) {
            return null;
        }
        $actorid = $actorid ?? (int) ($USER->id ?? 0);
        $assignable = array_map('intval', array_keys(get_assignable_roles(
            \context_course::instance((int) $course->id), ROLENAME_SHORT, false, $actorid)));
        $ids = array_values(array_diff($assignable, self::scoped_forbidden_role_ids()));
        if (!self::path_in_tenant((string) ($course->open_path ?? ''), $root)) {
            $ids = array_values(array_intersect($ids, self::learner_role_ids()));
        }
        return $ids;
    }

    /**
     * The role picker for an enrolment into $course: role id => label.
     *
     * One list for the enrol modal and the enrol CSV, so the two can never
     * disagree (ADR-031 follow-up): ENROL_HIDDEN_ROLE_SHORTNAMES for everyone,
     * then enrol_allowed_role_ids() for a scoped caller.
     *
     * @param \stdClass $course record carrying id and open_path
     * @param int|null $root the caller's tenant root; null = cross-tenant
     * @param int|null $actorid the enrolling user; defaults to the current user
     * @return array<int, \stdClass> role id => role record (id, shortname, name)
     */
    public static function enrol_role_choices(\stdClass $course, ?int $root,
                                              ?int $actorid = null): array {
        global $DB;
        $allowed = self::enrol_allowed_role_ids($course, $root, $actorid);
        $out = [];
        foreach ($DB->get_records('role', null, 'sortorder ASC', 'id, shortname, name') as $r) {
            if (in_array((string) $r->shortname, self::ENROL_HIDDEN_ROLE_SHORTNAMES, true)) {
                continue;
            }
            if ($allowed !== null && !in_array((int) $r->id, $allowed, true)) {
                continue;
            }
            $out[(int) $r->id] = $r;
        }
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a new course.
     *
     * Uses Moodle's create_course() so all events fire (course_created event,
     * gradebook setup, blocks added per format defaults, etc.).
     *
     * @param object $data  Form data with: fullname, shortname, category, format,
     *                      summary, summaryformat, plus optional open_* fields
     * @return int  New course ID
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Validate required fields.
        if (empty($data->fullname) || empty($data->shortname) || empty($data->category)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_courses');
        }

        // ADR-031: a scoped caller with no resolvable tenant creates nothing.
        $crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();
        $scopepath = $crosstenant ? '' : \local_sentientia_platform\tenant::scope_path();
        if ($scopepath === null) {
            throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
        }
        // ADR-031 (follow-up): the form offers a scoped caller only the
        // categories edit_category_options() lists; hold the data layer to it.
        if (!$crosstenant && !array_key_exists((int) $data->category, self::edit_category_options())) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }

        // Check shortname uniqueness.
        if ($DB->record_exists('course', ['shortname' => $data->shortname])) {
            throw new \moodle_exception('shortnametaken', 'local_sentientia_courses');
        }

        // Build course record. create_course() wants stdClass with specific fields.
        $course = new \stdClass();
        $course->category    = (int) $data->category;
        $course->fullname    = trim($data->fullname);
        $course->shortname   = trim($data->shortname);
        $course->idnumber    = $data->idnumber ?? '';
        $course->summary     = $data->summary ?? '';
        $course->summaryformat = $data->summaryformat ?? FORMAT_HTML;
        $course->format      = $data->format ?? 'topics';
        $course->numsections = (int) ($data->numsections ?? 5);
        $course->visible     = isset($data->visible) ? (int) $data->visible : 1;
        $course->startdate   = $data->startdate ?? time();
        $course->enddate     = $data->enddate ?? 0;
        $course->lang        = $data->lang ?? '';

        // Tenant scoping — derive open_path from organisation.
        // (open_costcenterid column does not exist on production — only open_path.)
        // ADR-031: the org must be inside a scoped caller's tenant.
        if (!empty($data->open_costcenterid)) {
            $orgpath = self::org_path_for_write((int) $data->open_costcenterid);
            if ($orgpath !== false) {
                $course->open_path = $orgpath;
            }
        }
        // ADR-031: a scoped caller may not create a course with no open_path -
        // the Manage Courses list shows those to every tenant. "No specific
        // organisation" means the caller's own tenant root.
        if (!$crosstenant && empty($course->open_path)) {
            $course->open_path = $scopepath;
        }

        // P1 #21 (2026-05-16) — open_coursecompletiondays. Closes audit
        // item #28 from parity-audit-2026-05-15/sentientia_courses.md. The
        // column already exists on mdl_course; we just had to start
        // populating it from the form. 0 = no deadline (Moodle convention).
        if (isset($data->open_coursecompletiondays)) {
            $course->open_coursecompletiondays
                = max(0, (int) $data->open_coursecompletiondays);
        }

        // Create via core API.
        $newcourse = create_course($course);

        return $newcourse->id;
    }

    /**
     * Update an existing course.
     *
     * @param int $courseid
     * @param object $data
     * @return bool
     * @throws \moodle_exception
     */
    public static function update(int $courseid, object $data): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $existing = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // ADR-031: the course being edited must be in the caller's tenant.
        self::require_course_write_access($existing);

        // Build update record.
        $course = new \stdClass();
        $course->id = $courseid;

        // Standard fields.
        $stdfields = ['fullname', 'shortname', 'idnumber', 'summary', 'summaryformat',
                      'format', 'visible', 'startdate', 'enddate', 'lang', 'category'];
        foreach ($stdfields as $field) {
            if (isset($data->$field)) {
                $course->$field = $data->$field;
            }
        }

        // ADR-031 (follow-up): a scoped caller may move a course only into a
        // category edit_category_options() offers them. Leaving it where it
        // is stays allowed (a course in a hidden category keeps it).
        if (isset($course->category) && (int) $course->category !== (int) $existing->category
                && !\local_sentientia_platform\tenant::is_cross_tenant()
                && !array_key_exists((int) $course->category, self::edit_category_options())) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }

        // Shortname uniqueness check.
        if (isset($course->shortname) && $course->shortname !== $existing->shortname) {
            if ($DB->record_exists_select('course',
                'shortname = :sn AND id != :id',
                ['sn' => $course->shortname, 'id' => $courseid])) {
                throw new \moodle_exception('shortnametaken', 'local_sentientia_courses');
            }
        }

        // Tenant scoping update — open_path is the canonical store.
        // ADR-031: re-homing is limited to orgs inside a scoped caller's
        // tenant. 0 ("No specific organisation") leaves open_path unchanged,
        // exactly as before.
        if (!empty($data->open_costcenterid)) {
            $orgpath = self::org_path_for_write((int) $data->open_costcenterid);
            if ($orgpath !== false) {
                $course->open_path = $orgpath;
            }
        }

        // P1 #21 — completion deadline. See create() for context.
        if (isset($data->open_coursecompletiondays)) {
            $course->open_coursecompletiondays
                = max(0, (int) $data->open_coursecompletiondays);
        }

        update_course($course);
        return true;
    }

    /**
     * Toggle course visibility (show/hide).
     *
     * @param int $courseid
     * @param bool|null $visible  null = toggle current state
     * @return bool  New visibility
     * @throws \moodle_exception
     */
    public static function toggle_visibility(int $courseid, ?bool $visible = null): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // '*' rather than 'id, visible, open_path': open_path is a BizLMS
        // column that a vanilla schema does not have.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // ADR-031: until 2026-09-25 any :visibility holder (every tenant
        // admin) could hide any tenant's course by id.
        self::require_course_write_access($course);

        $newstate = $visible ?? !((bool) $course->visible);

        // Use update_course so events fire and visibility cache is invalidated.
        $update = (object) [
            'id'      => $courseid,
            'visible' => $newstate ? 1 : 0,
            'visibleold' => $newstate ? 1 : 0,
        ];
        update_course($update);

        return $newstate;
    }

    /**
     * Delete a course.
     *
     * @param int $courseid
     * @return bool
     * @throws \moodle_exception
     */
    public static function delete(int $courseid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Block deleting site course (id=1).
        if ($courseid <= 1) {
            throw new \moodle_exception('cannotdeletesitecourse', 'local_sentientia_courses');
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // ADR-031 defence in depth: :delete is site-admin-only by default,
        // but a role it is granted to stays inside its own tenant.
        self::require_course_write_access($course);
        return delete_course($course, false);
    }
}

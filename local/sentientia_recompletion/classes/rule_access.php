<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * ADR-031 (2026-09-25): which rules, courses and history a caller may see
 * and change.
 *
 * Until 2026-09-25 this plugin resolved no tenant at all. :view (manager
 * archetype) listed every tenant's rules and reset history (names, emails,
 * compliance dates), and :manage (granted by db/install.php to the
 * tenant-admin role "administrator") let any tenant admin open any rule by
 * id and create rules with costcenterid 0 - which recompletion_engine reads
 * as ALL tenants, so a tenant admin's rule deleted every tenant's
 * completions, grades and quiz attempts on the daily cron.
 *
 * Now: cross-tenant callers (site admin, local/sentientia_platform:crosstenant)
 * keep the old behaviour, including global (costcenterid 0) rules. A scoped
 * caller sees and edits only their tenant's rules, every rule they create is
 * stamped with their tenant, and they see only their tenant's users' reset
 * history. A caller whose tenant does not resolve gets nothing.
 *
 * @package local_sentientia_recompletion
 */
class rule_access {

    private const RULES = 'local_sentientia_recompletion_rules';

    /**
     * The caller's tenant root: 0 = cross-tenant (no restriction), N > 0 =
     * scoped to tenant N. Refuses a caller with no resolvable tenant.
     *
     * @throws \moodle_exception error_outoftenant
     */
    public static function caller_root(): int {
        $scope = tenant::scope_path();
        if ($scope === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if ($scope === '') {
            return 0;
        }
        $root = (int) substr($scope, 1);
        tenant::assert_valid($root);
        return $root;
    }

    /**
     * Load a rule and refuse unless the caller may edit it. A scoped caller
     * may never open a global (costcenterid 0) rule: viewer_can_access(0)
     * would pass for a caller whose own root is 0.
     *
     * @throws \moodle_exception error_outoftenant (or dml_missing_record_exception)
     */
    public static function require_rule(int $ruleid): \stdClass {
        global $DB;
        $rule = $DB->get_record(self::RULES, ['id' => $ruleid], '*', MUST_EXIST);
        $root = self::caller_root();
        if ($root > 0 && (int) $rule->costcenterid !== $root) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $rule;
    }

    /**
     * The costcenterid a rule the caller saves must carry. A scoped caller's
     * rules are always their tenant's; on update the stored value is kept
     * (require_rule() already proved it is theirs). Cross-tenant callers keep
     * the old behaviour: new rules are global (0), updates leave it alone.
     *
     * @param \stdClass|null $existing the rule being updated, null on create
     * @return int|null null = do not write the column
     */
    public static function costcenterid_for_save(?\stdClass $existing): ?int {
        $root = self::caller_root();
        if ($existing !== null) {
            return null;
        }
        return $root;
    }

    /**
     * WHERE fragment over the rules table for the rules list.
     *
     * @return array{0: string, 1: array}
     */
    public static function rules_filter(): array {
        $scope = tenant::scope_path();
        if ($scope === null) {
            return ['1=0', []];
        }
        if ($scope === '') {
            return ['1=1', []];
        }
        return ['costcenterid = :rarulesroot', ['rarulesroot' => (int) substr($scope, 1)]];
    }

    /**
     * WHERE fragment over {user} u for the reset history. A scoped caller
     * sees only rows about their own tenant's users (history rows carry no
     * tenant of their own; bulk rows have ruleid 0), and so not rows whose
     * user was redacted (userid 0) - those stay site-admin only.
     *
     * @param string $alias user table alias
     * @return array{0: string, 1: array} path_filter(): '1=1' cross-tenant, '1=0' no tenant
     */
    public static function history_user_filter(string $alias = 'u'): array {
        return tenant::path_filter($alias);
    }

    /**
     * WHERE fragment for the courses the caller may point a rule at:
     * cross-tenant every course; scoped their tenant tree, legacy unpathed
     * courses (the tolerance local_sentientia_courses' own list applies) and
     * courses shared to their tenant; no tenant nothing. The engine resets
     * only the rule's tenant's users either way; this keeps other tenants'
     * course catalogues out of the picker.
     *
     * @param string $alias course table alias
     * @return array{0: string, 1: array}
     */
    public static function course_filter(string $alias = 'c'): array {
        global $DB;
        $scope = tenant::scope_path();
        if ($scope === null) {
            return ['1=0', []];
        }
        if ($scope === '') {
            return ['1=1', []];
        }
        [$sql, $params] = tenant::path_descendant_filter($scope, $alias, 'open_path', 'racs', true);
        if ($DB->get_manager()->table_exists('local_sentientia_courses_tenant_share')) {
            $sql = "($sql OR EXISTS (SELECT 1 FROM {local_sentientia_courses_tenant_share} rash
                                     WHERE rash.courseid = {$alias}.id
                                       AND rash.tenant_id = :racstenant
                                       AND rash.status = :racsstatus))";
            $params['racstenant'] = (int) substr($scope, 1);
            $params['racsstatus'] = 'active';
        }
        return [$sql, $params];
    }

    /**
     * WHERE fragment over {course} $alias for the courses the edit form lists:
     * course_filter() plus "completion enabled, not the front page".
     *
     * @param string $alias course table alias
     * @return array{0: string, 1: array}
     */
    private static function course_option_filter(string $alias = 'c'): array {
        [$csql, $cparams] = self::course_filter($alias);
        return [
            "{$alias}.id <> :racsite AND {$alias}.enablecompletion = 1 AND $csql",
            ['racsite' => SITEID] + $cparams,
        ];
    }

    /**
     * The courses the caller may point a rule at, for edit.php's picker:
     * completion enabled, never the front page, limited by course_filter()
     * (cross-tenant every course; scoped their tenant tree, legacy unpathed
     * courses and courses shared to their tenant; no tenant none).
     *
     * ADR-031 follow-up (2026-09-25): wave 1 handed course_filter('c') to
     * get_records_select('course', ...), which queries {course} WITHOUT an
     * alias. Every scoped caller hit "Unknown column 'c.open_path'" (MySQL /
     * MariaDB) or "missing FROM-clause entry for table c" (PostgreSQL), so no
     * tenant admin could open edit.php to create or change their own rules.
     * This is the aliased query, in one testable place.
     *
     * @return \stdClass[] id => {id, fullname, shortname}, ordered by fullname
     */
    public static function course_options(): array {
        global $DB;
        [$sql, $params] = self::course_option_filter('c');
        return $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname
               FROM {course} c
              WHERE $sql
           ORDER BY c.fullname ASC, c.id ASC", $params);
    }

    /**
     * The course id a submitted rule form may carry: 0 ("all courses") or one
     * of course_options(). Anything else is refused, never coerced.
     *
     * The picker is a select, and a select element drops a submitted value
     * that is not one of its options: exportValue() returns null, which
     * edit.php used to cast to 0 = "all courses". So a tampered request
     * naming another tenant's course silently became an all-courses rule.
     * The engine still reset only the caller's own users, so nothing leaked,
     * but the caller got a far wider rule than the one they asked for. Pass
     * the RAW submitted value (MoodleQuickForm::getSubmitValue()), not the
     * exported one.
     *
     * @param mixed $submitted raw submitted courseid
     * @return int the course id; 0 = all courses
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_course_option($submitted): int {
        global $DB;
        if (is_array($submitted) || $submitted === null
                || !preg_match('/^\d+$/', trim((string) $submitted))) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        // A caller with no tenant may not save any rule, "all courses" included.
        self::caller_root();
        $courseid = (int) trim((string) $submitted);
        if ($courseid === 0) {
            return 0;
        }
        [$sql, $params] = self::course_option_filter('c');
        if (!$DB->record_exists_sql("SELECT 1 FROM {course} c WHERE c.id = :racoptid AND $sql",
                ['racoptid' => $courseid] + $params)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $courseid;
    }

    /**
     * Refuse unless $courseid (0 = "all courses") is one the caller may use.
     *
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_course(int $courseid): void {
        global $DB;
        if ($courseid <= 0) {
            return;
        }
        [$csql, $cparams] = self::course_filter('c');
        if (!$DB->record_exists_sql("SELECT 1 FROM {course} c WHERE c.id = :racid AND $csql",
                ['racid' => $courseid] + $cparams)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The learner dashboard's fallback "Recommended for You" rail.
 *
 * When the skills-first recommendations are off or empty, the dashboard
 * recommends the newest courses in the categories of the learner's enrolled
 * courses. That query used to live inline in layout/dashboard.php with no
 * tenant filter at all, and categories are shared between tenants: on the
 * 2026-11-18 surface walk an Airpay (/1) learner was recommended a ZEEA (/177)
 * course, and its link bounced back to the dashboard (the learner can neither
 * open nor enrol in it).
 *
 * The rail now offers only what the course catalogue would let the learner
 * open (local_sentientia_catalog\catalog_manager::assert_course_visible_to_viewer()):
 *
 *   - visible, not the site course, an ordinary course (not a BizLMS exam or
 *     forum pseudo-course, catalog_manager::ordinary_courses_condition());
 *   - owned by the learner's tenant tree ('/N' or '/N/...', never '/NN'), or
 *     shared to the learner's tenant through local_sentientia_courses, the
 *     same SQL the catalogue browses with
 *     (sharing_manager::build_catalog_filter_sql());
 *   - a cross-tenant viewer (site admin, :crosstenant holder) is not
 *     restricted; a viewer whose tenant cannot be resolved gets nothing.
 *
 * A database without course.open_path (a vanilla Moodle schema) has no
 * tenants, so the tenant condition is skipped there, as the catalogue does.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_recommendations {

    /**
     * Newest courses in the categories of the learner's enrolled courses that
     * the learner may open from the catalogue.
     *
     * @param \stdClass $user The learner (id and open_path are read).
     * @param int[] $enrolledids Ids of the learner's enrolled courses; these are never recommended.
     * @param int $limit Maximum number of courses.
     * @return \stdClass[] Rows with id, fullname, summary, category, categoryname, newest first.
     */
    public static function same_category_courses(\stdClass $user, array $enrolledids, int $limit = 3): array {
        global $DB;

        $enrolledids = array_values(array_unique(array_filter(array_map('intval', $enrolledids))));
        if (empty($enrolledids) || $limit <= 0) {
            return [];
        }

        $scope = self::course_visibility_sql($user, 'c');
        if ($scope === null) {
            return [];
        }
        [$scopesql, $scopeparams] = $scope;

        [$enrolsql, $enrolparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'enc');
        $categories = $DB->get_fieldset_sql(
            "SELECT DISTINCT category FROM {course} WHERE id $enrolsql",
            $enrolparams
        );
        if (empty($categories)) {
            return [];
        }

        [$catsql, $catparams] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
        $ordinary = self::ordinary_courses_sql('c');

        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.summary, c.category, cc.name AS categoryname
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.category $catsql
                AND c.id $exsql
                AND c.visible = 1 AND c.id > 1
                AND $ordinary
                AND $scopesql
           ORDER BY c.timecreated DESC, c.id DESC",
            array_merge($catparams, $exparams, $scopeparams),
            0,
            $limit
        );
        return array_values($rows);
    }

    /**
     * The catalogue's tenant rule for courses, as SQL on course alias $alias.
     *
     * @param \stdClass $user The viewer (id and open_path are read).
     * @param string $alias Course table alias.
     * @return array{0: string, 1: array}|null [sql, params], or null when the viewer may open no course.
     */
    public static function course_visibility_sql(\stdClass $user, string $alias = 'c'): ?array {
        global $DB;

        if (!array_key_exists('open_path', $DB->get_columns('course'))) {
            // Vanilla schema: no tenants to scope by (the catalogue degrades the same way).
            return ['1 = 1', []];
        }
        if (!class_exists('\\local_sentientia_platform\\tenant')) {
            // Tenanted schema but no tenant resolver: fail closed rather than show every tenant's courses.
            return null;
        }

        $scopepath = \local_sentientia_platform\tenant::scope_path($user);
        if ($scopepath === null) {
            return null;
        }
        if ($scopepath === '') {
            return ['1 = 1', []];
        }

        if (class_exists('\\local_sentientia_courses\\sharing_manager')) {
            // Owned ('/N' or '/N/...') OR actively shared to tenant N: the catalogue's own browse filter.
            return \local_sentientia_courses\sharing_manager::build_catalog_filter_sql(
                $alias, (int) ltrim($scopepath, '/'));
        }
        // No sharing plugin: owned courses only, '/'-bounded.
        return \local_sentientia_platform\tenant::path_descendant_filter($scopepath, $alias, 'open_path', 'recown');
    }

    /**
     * "Ordinary courses only" (no BizLMS exam or forum pseudo-course), as the catalogue lists them.
     *
     * @param string $alias Course table alias.
     * @return string SQL condition without parameters.
     */
    private static function ordinary_courses_sql(string $alias): string {
        if (class_exists('\\local_sentientia_catalog\\catalog_manager')) {
            return \local_sentientia_catalog\catalog_manager::ordinary_courses_condition($alias);
        }
        return '1 = 1';
    }

    /**
     * Where a recommendation card links: the catalogue's course page, which
     * shows the enrol or request action for a course the learner is not in,
     * or the course itself when the catalogue plugin is not installed.
     *
     * @param int $courseid
     * @return \moodle_url
     */
    public static function course_url(int $courseid): \moodle_url {
        if (class_exists('\\local_sentientia_catalog\\catalog_manager')) {
            return new \moodle_url('/local/sentientia_catalog/course.php', ['id' => $courseid]);
        }
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }
}

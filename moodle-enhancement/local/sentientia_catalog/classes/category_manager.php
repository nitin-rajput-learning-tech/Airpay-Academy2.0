<?php
namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Category manager — wraps queries against local_sentientia_course_category.
 *
 * Replaces direct DB queries found in coursedetails.php (4 queries),
 * course.php (1 query), and mycourses.php (1 query).
 *
 * The table stores custom course categories separate from Moodle's
 * course_categories. It is owned by local_sentientia_courses; the BizLMS
 * local_custom_category rows arrive in it through the ADR-032 course_lookups
 * import with their ids kept (course.open_categoryid points at them).
 *
 * ADR-032 course_lookups code fix 1 (2026-10-01): this class used to read the
 * BizLMS table local_custom_category directly. That legacy read is gone, so no
 * reader depends on a BizLMS table after cutover, and the two list methods
 * (get_root_categories, get_children) are now bounded by the caller's tenant:
 * a scoped caller sees the categories of their own tenant tree only, a
 * cross-tenant caller sees all of them, and a category with no tenant path
 * (tenant_path NULL) is visible to cross-tenant callers only (ADR-031: fail
 * closed). The by-id lookups (get, get_name, get_with_parent) stay unscoped:
 * they name the category of a course the viewer can already see.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {

    /** @var string Table owned by local_sentientia_courses (filled by the ADR-032 import). */
    private const TABLE = 'local_sentientia_course_category';

    /**
     * Get category name by ID.
     *
     * Replaces: $DB->get_field('local_custom_category', 'fullname', ['id' => $id])
     *
     * @param int $categoryid
     * @return string  Category name or empty string
     */
    public static function get_name(int $categoryid): string {
        global $DB;

        if (empty($categoryid)) {
            return '';
        }

        if (!self::table_exists()) {
            return '';
        }

        $name = $DB->get_field(self::TABLE, 'fullname', ['id' => $categoryid]);
        return $name ?: '';
    }

    /**
     * Get category record by ID.
     *
     * Replaces: $DB->get_record_sql("SELECT id, fullname, parentid FROM {local_custom_category} WHERE id=:id")
     *
     * @param int $categoryid
     * @return object|false  {id, fullname, parentid}
     */
    public static function get(int $categoryid) {
        global $DB;

        if (empty($categoryid) || !self::table_exists()) {
            return false;
        }

        return $DB->get_record(self::TABLE, ['id' => $categoryid], 'id, fullname, parentid');
    }

    /**
     * Get category with parent name (breadcrumb).
     *
     * Replaces the duplicated pattern in coursedetails.php lines 77-82 and 225-230.
     *
     * @param int $categoryid
     * @return object  {name, parent_name, full_path}
     */
    public static function get_with_parent(int $categoryid): object {
        $result = (object) ['name' => '', 'parent_name' => '', 'full_path' => ''];

        $cat = self::get($categoryid);
        if (!$cat) {
            return $result;
        }

        $result->name = $cat->fullname;

        if (!empty($cat->parentid)) {
            $result->parent_name = self::get_name((int) $cat->parentid);
        }

        $result->full_path = !empty($result->parent_name)
            ? $result->parent_name . ' / ' . $result->name
            : $result->name;

        return $result;
    }

    /**
     * Get all top-level categories the current user may see.
     *
     * @return array
     */
    public static function get_root_categories(): array {
        return self::list_children(0);
    }

    /**
     * Get the children of a category that the current user may see.
     *
     * @param int $parentid
     * @return array
     */
    public static function get_children(int $parentid): array {
        return self::list_children($parentid);
    }

    /**
     * Children of a parent, bounded by the caller's tenant (ADR-031).
     *
     * tenant::path_filter() gives a cross-tenant caller no restriction, a scoped caller their tenant root's
     * exact path and its '/'-bounded descendants, and a caller with no resolvable tenant nothing. A category
     * with a NULL tenant_path matches none of the scoped forms, so only a cross-tenant caller lists it.
     *
     * @param int $parentid
     * @return array id => record
     */
    private static function list_children(int $parentid): array {
        global $DB;

        if (!self::table_exists()) {
            return [];
        }

        [$tenantsql, $tenantparams] = \local_sentientia_platform\tenant::path_filter('', 'tenant_path');
        return $DB->get_records_select(self::TABLE, "parentid = :catparentid AND {$tenantsql}",
            ['catparentid' => $parentid] + $tenantparams, 'fullname ASC');
    }

    /**
     * Check if the table exists (guard against a plugin that has not been upgraded yet).
     *
     * @return bool
     */
    private static function table_exists(): bool {
        global $DB;
        static $exists = null;

        if ($exists === null) {
            $exists = $DB->get_manager()->table_exists(self::TABLE);
        }

        return $exists;
    }
}

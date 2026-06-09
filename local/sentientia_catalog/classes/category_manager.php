<?php
namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Category manager — wraps queries against local_custom_category.
 *
 * Replaces direct DB queries found in coursedetails.php (4 queries),
 * course.php (1 query), and mycourses.php (1 query).
 *
 * The local_custom_category table is a BizLMS table that stores
 * custom course categories separate from Moodle's course_categories.
 * We read from it but don't modify it until Phase 7 migration.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {

    /** @var string BizLMS table — we read from this during transition. */
    private const TABLE = 'local_custom_category';

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
     * Get all top-level categories.
     *
     * @return array
     */
    public static function get_root_categories(): array {
        global $DB;

        if (!self::table_exists()) {
            return [];
        }

        return $DB->get_records(self::TABLE, ['parentid' => 0], 'fullname ASC');
    }

    /**
     * Get children of a category.
     *
     * @param int $parentid
     * @return array
     */
    public static function get_children(int $parentid): array {
        global $DB;

        if (!self::table_exists()) {
            return [];
        }

        return $DB->get_records(self::TABLE, ['parentid' => $parentid], 'fullname ASC');
    }

    /**
     * Check if the table exists (guard against missing BizLMS plugin).
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

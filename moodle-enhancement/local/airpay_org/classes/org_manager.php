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

namespace local_airpay_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Organization hierarchy manager.
 *
 * CRUD operations for the org tree. Replaces direct queries against
 * {local_costcenter} found throughout dashboard.php, compliance_engine,
 * analytics_manager, and homepage.php.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_manager {

    /** @var string Primary table — use this after migration. */
    private const TABLE = 'local_airpay_org';

    /** @var string Legacy table — fallback during transition. */
    private const LEGACY_TABLE = 'local_costcenter';

    /**
     * Get an org record by ID.
     *
     * Reads from local_airpay_org first, falls back to local_costcenter.
     *
     * @param int $orgid
     * @return object|false
     */
    public static function get(int $orgid) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $orgid]);
        if ($record) {
            return $record;
        }

        return self::legacy_get($orgid);
    }

    /**
     * Get org display name by ID.
     *
     * Replaces: $DB->get_field('local_costcenter', 'fullname', [...])
     * Used in dashboard.php line 282, analytics drilldown, etc.
     *
     * @param int $orgid
     * @return string
     */
    public static function get_name(int $orgid): string {
        global $DB;

        $name = $DB->get_field(self::TABLE, 'fullname', ['id' => $orgid]);
        if ($name !== false) {
            return $name;
        }

        // Legacy fallback.
        $name = self::legacy_field($orgid, 'fullname');
        return $name ?: '';
    }

    /**
     * Get org by hierarchical path.
     *
     * Replaces: $DB->get_field('local_costcenter', 'fullname', ['path' => $toporg])
     *
     * @param string $path  e.g. "/1" or "/77"
     * @return object|false
     */
    public static function get_by_path(string $path) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['path' => $path]);
        if ($record) {
            return $record;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_record(self::LEGACY_TABLE, ['path' => $path]);
        }

        return false;
    }

    /**
     * Get name by path.
     *
     * Shorthand for dashboard tenant scope label.
     *
     * @param string $path
     * @return string
     */
    public static function get_name_by_path(string $path): string {
        $org = self::get_by_path($path);
        return $org->fullname ?? '';
    }

    /**
     * Get all child orgs under a parent.
     *
     * Replaces the compliance_engine pattern of querying children by parentid.
     *
     * @param int  $parentid
     * @param bool $visibleonly
     * @return array
     */
    public static function get_children(int $parentid, bool $visibleonly = true): array {
        global $DB;

        $conditions = ['parentid' => $parentid];
        if ($visibleonly) {
            $conditions['visible'] = 1;
        }

        $records = $DB->get_records(self::TABLE, $conditions, 'sortorder ASC');
        if (!empty($records)) {
            return $records;
        }

        // Legacy fallback.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records(self::LEGACY_TABLE, $conditions, 'sortorder ASC');
        }

        return [];
    }

    /**
     * Get all orgs under a path prefix (descendants).
     *
     * Replaces the pattern: WHERE path LIKE '/77/%'
     * Used in homepage, catalog, compliance for tenant scoping.
     *
     * @param string $pathprefix  e.g. "/77"
     * @return array  Array of org records
     */
    public static function get_descendants(string $pathprefix): array {
        global $DB;

        $records = $DB->get_records_select(
            self::TABLE,
            "path LIKE :pathlike",
            ['pathlike' => $pathprefix . '/%']
        );

        if (!empty($records)) {
            return $records;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records_select(
                self::LEGACY_TABLE,
                "path LIKE :pathlike",
                ['pathlike' => $pathprefix . '/%']
            );
        }

        return [];
    }

    /**
     * Get all root-level tenants (depth=1, parentid=0).
     *
     * @return array
     */
    public static function get_tenants(): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['depth' => 1], 'sortorder ASC');
        if (!empty($records)) {
            return $records;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records(self::LEGACY_TABLE, ['parentid' => 0], 'id ASC');
        }

        return [];
    }

    /**
     * Get all org IDs under a path (for IN clause queries).
     *
     * @param string $pathprefix
     * @return array  Array of int IDs
     */
    public static function get_descendant_ids(string $pathprefix): array {
        $descendants = self::get_descendants($pathprefix);
        return array_map(function ($o) {
            return (int) $o->id;
        }, $descendants);
    }

    /**
     * Fallback read from legacy BizLMS table.
     *
     * @param int $orgid
     * @return object|false
     */
    private static function legacy_get(int $orgid) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LEGACY_TABLE)) {
            return false;
        }

        return $DB->get_record(self::LEGACY_TABLE, ['id' => $orgid]);
    }

    /**
     * Fallback field read from legacy table.
     *
     * @param int    $orgid
     * @param string $field
     * @return mixed|false
     */
    private static function legacy_field(int $orgid, string $field) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LEGACY_TABLE)) {
            return false;
        }

        return $DB->get_field(self::LEGACY_TABLE, $field, ['id' => $orgid]);
    }
}

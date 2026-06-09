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

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant manager — open_path parsing, tenant detection, scope helpers.
 *
 * Centralizes the scattered open_path extraction logic found in 27+ files.
 * Every plugin should call these methods instead of parsing open_path inline.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_manager {

    /**
     * Extract tenant (root org) ID from a user's open_path.
     *
     * Replaces the pattern scattered across 27+ files:
     *   $parts = explode('/', $USER->open_path ?? '');
     *   $costcenterid = (int)($parts[1] ?? 0);
     *
     * @param object|null $user  User object (null = current user)
     * @return int  Tenant ID (0 if not set)
     */
    public static function get_tenant_id(?object $user = null): int {
        global $USER;
        $user = $user ?? $USER;

        $openpath = $user->open_path ?? '';
        if (empty($openpath)) {
            return 0;
        }

        $parts = explode('/', $openpath);
        return (int) ($parts[1] ?? 0);
    }

    /**
     * Extract the full org path prefix for tenant-scoped queries.
     *
     * Returns "/{tenant_id}" suitable for LIKE queries.
     * Replaces the dashboard.php pattern of building $toporg.
     *
     * @param object|null $user
     * @return string  e.g. "/1" or "/77", or empty string
     */
    public static function get_tenant_path(?object $user = null): string {
        $tenantid = self::get_tenant_id($user);
        if ($tenantid <= 0) {
            return '';
        }
        return '/' . $tenantid;
    }

    /**
     * Get the LIKE filter for tenant-scoped user queries.
     *
     * Returns the pattern for: WHERE u.open_path LIKE :upath
     *
     * @param object|null $user
     * @return string  e.g. "/1/%" or empty string
     */
    public static function get_user_path_filter(?object $user = null): string {
        $path = self::get_tenant_path($user);
        if (empty($path)) {
            return '';
        }
        return $path . '/%';
    }

    /**
     * Get the public (guest-facing) tenant ID.
     *
     * Reads from config first, falls back to auto-detection by shortname.
     * Replaces the homepage.php pattern.
     *
     * @return int  Public tenant ID (default 77)
     */
    public static function get_public_tenant_id(): int {
        global $DB;

        $configured = get_config('local_sentientia_org', 'public_tenant_id');
        if (!empty($configured)) {
            return (int) $configured;
        }

        // Auto-detect by shortname.
        $table = self::get_org_table();
        $record = $DB->get_record_select(
            $table,
            "shortname IN ('external', 'public')",
            null,
            'id',
            IGNORE_MULTIPLE
        );

        return $record ? (int) $record->id : 77;
    }

    /**
     * Get the public tenant path prefix for queries.
     *
     * @return string  e.g. "/77"
     */
    public static function get_public_tenant_path(): string {
        return '/' . self::get_public_tenant_id();
    }

    /**
     * Check if the current user belongs to a specific tenant.
     *
     * @param int         $tenantid
     * @param object|null $user
     * @return bool
     */
    public static function is_tenant_member(int $tenantid, ?object $user = null): bool {
        return self::get_tenant_id($user) === $tenantid;
    }

    /**
     * Check if a user is in the public tenant.
     *
     * @param object|null $user
     * @return bool
     */
    public static function is_public_tenant_user(?object $user = null): bool {
        return self::get_tenant_id($user) === self::get_public_tenant_id();
    }

    /**
     * Get tenant name for the current user.
     *
     * @param object|null $user
     * @return string
     */
    public static function get_tenant_name(?object $user = null): string {
        $tenantid = self::get_tenant_id($user);
        if ($tenantid <= 0) {
            return '';
        }
        return org_manager::get_name($tenantid);
    }

    /**
     * Check if a user is a manager (has direct reports via open_supervisorid).
     *
     * Replaces the dashboard.php pattern at line 153-162.
     *
     * @param int|null $userid  (null = current user)
     * @return bool
     */
    public static function is_manager(?int $userid = null): bool {
        global $DB, $USER;
        $userid = $userid ?? $USER->id;

        return $DB->record_exists('user', ['open_supervisorid' => $userid, 'deleted' => 0]);
    }

    /**
     * Get direct reports for a manager.
     *
     * @param int|null $managerid  (null = current user)
     * @return array  User records
     */
    public static function get_direct_reports(?int $managerid = null): array {
        global $DB, $USER;
        $managerid = $managerid ?? $USER->id;

        return $DB->get_records('user', [
            'open_supervisorid' => $managerid,
            'deleted'           => 0,
            'suspended'         => 0,
        ], 'lastname ASC, firstname ASC');
    }

    /**
     * Count direct reports for a manager.
     *
     * @param int|null $managerid
     * @return int
     */
    public static function count_direct_reports(?int $managerid = null): int {
        global $DB, $USER;
        $managerid = $managerid ?? $USER->id;

        return $DB->count_records('user', [
            'open_supervisorid' => $managerid,
            'deleted'           => 0,
        ]);
    }

    /**
     * Determine which org table to use (prefers sentientia_org, falls back to costcenter).
     *
     * @return string  Table name without braces
     */
    private static function get_org_table(): string {
        global $DB;

        $dbman = $DB->get_manager();

        // Prefer our table if it has data.
        if ($dbman->table_exists('local_sentientia_org')) {
            $count = $DB->count_records('local_sentientia_org');
            if ($count > 0) {
                return 'local_sentientia_org';
            }
        }

        // Fallback to BizLMS.
        if ($dbman->table_exists('local_costcenter')) {
            return 'local_costcenter';
        }

        return 'local_sentientia_org';
    }
}

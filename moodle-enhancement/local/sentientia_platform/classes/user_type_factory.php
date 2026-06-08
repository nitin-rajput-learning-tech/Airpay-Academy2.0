<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Resolve a userid to its `user_type_provider` instance.
 *
 * ADR-017 Phase 2 (C1.2). Reads `local_airpay_user_type.user_type` for
 * the given userid, instantiates the matching provider, caches per-
 * request for performance.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

class user_type_factory {

    /** @var array<int, user_type_provider> Per-request instance cache */
    private static $cache = [];

    /**
     * Get the provider for a given userid. Caches per-request so
     * repeated lookups in the same render don't re-query.
     *
     * For users without a row in `local_airpay_user_type` (legacy
     * pre-backfill or freshly-created), returns the employee provider
     * as the defensive default — matches the §Resolution rule.
     *
     * @param int $userid
     * @return user_type_provider
     */
    public static function for_user(int $userid): user_type_provider {
        if (isset(self::$cache[$userid])) {
            return self::$cache[$userid];
        }

        global $DB;
        $row = $DB->get_record('local_airpay_user_type',
            ['userid' => $userid], 'user_type');
        $type = $row ? $row->user_type : 'employee';

        $provider = self::instantiate($type);
        self::$cache[$userid] = $provider;
        return $provider;
    }

    /**
     * Instantiate the provider class for a given type. Throws
     * `coding_exception` for unknown types.
     *
     * @param string $type
     * @return user_type_provider
     */
    public static function instantiate(string $type): user_type_provider {
        return match ($type) {
            'employee'         => new user_type\employee_provider(),
            'consumer'         => new user_type\consumer_provider(),
            'partner_employee' => new user_type\partner_employee_provider(),
            'operator'         => new user_type\operator_provider(),
            default            => throw new \coding_exception(
                "Unknown user_type: {$type}"),
        };
    }

    /**
     * Clear the per-request cache. Used by tests + by the
     * classify_existing_users CLI after writes.
     */
    public static function clear_cache(): void {
        self::$cache = [];
    }

    /**
     * Sentinel list of all valid user_type IDs. Stable for the v1
     * 4-type model; future v2 additions land here first.
     *
     * @return array<int, string>
     */
    public static function all_types(): array {
        return ['employee', 'consumer', 'partner_employee', 'operator'];
    }
}

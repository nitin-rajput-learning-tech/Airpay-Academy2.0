<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Org-hierarchy seam — ADR-020, Sentientia independence Wave 3.1.
 *
 * The sanctioned way to read a user's manager relationship, instead of touching
 * the BizLMS `$user->open_supervisorid` column directly (the SOFT coupling in
 * docs/DEPRECATION-SCHEDULE.md row 8).
 *
 * This is the **additive seam only** — Wave 3.1. Behind a default-ON
 * `org_legacy` flag it reads the legacy `open_supervisorid`, so behaviour is
 * identical to current production. When a future wave builds the Sentientia org
 * model (`local_sentientia_org_*` tables, ADR-020 §2 — gated on Nitin's go +
 * a clone-DB rehearsal), flipping the flag OFF switches the source; until then
 * the OFF path safely falls back to legacy, so it can never break.
 *
 * Scope note: only the manager-id ACCESSOR ships in 3.1 (it reads a record
 * property, so it is unit-testable on vanilla Moodle). Reverse lookups
 * (is_manager / direct reports) + unit-tree walks query the BizLMS-extended
 * user/costcenter tables, which don't exist on a vanilla Moodle test DB — they
 * arrive in Wave 3.2 alongside the `local_sentientia_org_*` schema.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org {

    /** Sentinel for "no manager". */
    public const NO_MANAGER = 0;

    /**
     * Is the legacy open_supervisorid / local_costcenter resolver active?
     *
     * Default ON: an unset config is treated as enabled so production behaviour
     * never changes implicitly.
     */
    public static function use_legacy_costcenter(): bool {
        $v = get_config('local_sentientia_core', 'org_legacy');
        return $v === false ? true : (bool) $v;
    }

    /**
     * Resolve a user's manager (line-manager) user id.
     *
     * @param \stdClass $user A user record carrying (at least) open_supervisorid.
     * @return int The manager's user id, or self::NO_MANAGER (0) if none.
     */
    public static function manager_id_of(\stdClass $user): int {
        if (self::use_legacy_costcenter()) {
            return self::legacy_manager_id($user);
        }
        // Wave 3.2+: the Sentientia org model. Not built yet — fall back to
        // legacy so the OFF state can be exercised without breaking manager UX.
        debugging('local_sentientia_core: Sentientia org model not yet available; '
            . 'falling back to legacy open_supervisorid.', DEBUG_DEVELOPER);
        return self::legacy_manager_id($user);
    }

    /**
     * Resolve the manager for the current $USER.
     *
     * @return int Manager user id, or self::NO_MANAGER (0) if logged out / none.
     */
    public static function manager_id_for_current_user(): int {
        global $USER;
        if (empty($USER->id)) {
            return self::NO_MANAGER;
        }
        return self::manager_id_of($USER);
    }

    /**
     * Legacy resolver: the BizLMS open_supervisorid column.
     *
     * @param \stdClass $user
     * @return int
     */
    private static function legacy_manager_id(\stdClass $user): int {
        return (int) ($user->open_supervisorid ?? self::NO_MANAGER);
    }
}

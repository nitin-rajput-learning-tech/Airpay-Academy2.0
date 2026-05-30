<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant identity seam — ADR-019, Sentientia independence Wave 2.
 *
 * This is the SINGLE abstraction the rest of Sentientia should call to
 * resolve a user's tenant, instead of reading `$USER->open_path` directly
 * (the hard BizLMS coupling catalogued in docs/DEPRECATION-SCHEDULE.md row 7).
 *
 * Today it delegates to the legacy BizLMS open_path parser
 * (`local_airpay_core\tenant`) behind a default-ON flag, so behaviour is
 * byte-identical to current production. A future wave flips
 * `tenant_identity_legacy` OFF to read from a Sentientia-owned tenant
 * registry; until that registry exists (ADR-018 Wave 3+), the OFF path
 * safely falls back to legacy resolution so nothing can break.
 *
 * Migration of the ~24 existing `open_path` call sites onto this seam is a
 * SEPARATE, staged step (not done in this scaffold commit).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_identity {

    /** Sentinel returned when no tenant can be resolved. */
    public const NO_TENANT = 0;

    /**
     * Is the legacy open_path resolver active?
     *
     * Default ON: an unset config (fresh install / pre-settings) is treated
     * as enabled so production behaviour never changes implicitly.
     */
    public static function use_legacy_open_path(): bool {
        $v = get_config('local_sentientia_core', 'tenant_identity_legacy');
        return $v === false ? true : (bool) $v;
    }

    /**
     * Resolve a user's tenant root id.
     *
     * @param \stdClass $user A user record carrying (at least) open_path.
     * @return int Tenant root id, or self::NO_TENANT (0) if unresolved.
     */
    public static function root_for_user(\stdClass $user): int {
        if (self::use_legacy_open_path()) {
            return self::legacy_root($user);
        }
        // Wave 3+: the Sentientia tenant registry. Not built yet — fall back
        // to legacy so the OFF state can be exercised without breaking auth.
        debugging('local_sentientia_core: Sentientia tenant registry not yet '
            . 'available; falling back to legacy open_path resolution.',
            DEBUG_DEVELOPER);
        return self::legacy_root($user);
    }

    /**
     * Resolve the tenant root for the current $USER.
     *
     * @return int Tenant root id, or self::NO_TENANT (0) if logged out.
     */
    public static function root_for_current_user(): int {
        global $USER;
        if (empty($USER->id)) {
            return self::NO_TENANT;
        }
        return self::root_for_user($USER);
    }

    /**
     * Legacy resolver: derive the tenant root from the BizLMS open_path.
     *
     * Delegates to `local_airpay_core\tenant` when present (single source of
     * truth); otherwise parses inline with the identical algorithm so the
     * seam carries no hard dependency on local_airpay_core.
     *
     * @param \stdClass $user
     * @return int
     */
    private static function legacy_root(\stdClass $user): int {
        if (class_exists('\local_airpay_core\tenant')) {
            return \local_airpay_core\tenant::root_for_user($user);
        }
        // Inline fallback — mirrors local_airpay_core\tenant::root_for_user().
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        if (!isset($parts[0]) || !ctype_digit($parts[0])) {
            return self::NO_TENANT;
        }
        return (int) $parts[0];
    }
}

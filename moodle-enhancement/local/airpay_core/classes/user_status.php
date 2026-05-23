<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * User-status helper — P0 borrow #10 from Moodle 5.2.
 *
 * Surfaces a single source of truth for "is this user currently
 * suspended/deleted/active" so renderers and reports can append a status
 * badge to the user's name. In 5.1 the only way to know was to query
 * mdl_user.suspended per-row, which is both slow and inconsistent across
 * report renderers.
 *
 * Cache strategy
 * --------------
 * One DB hit per request per batch of userids. The cache lives only for
 * the request (in-process static), so updates to user records take
 * effect on the next page load — no cross-request invalidation needed.
 *
 * Tenant safety
 * --------------
 * This helper does NOT scope by tenant when looking up status. A
 * suspended user is suspended regardless of who is looking, and that
 * status is not PII (it's already visible to anyone with site:viewreports).
 * Tenant gating happens at the WS layer and at the report renderer that
 * decides whether to call us at all.
 *
 * @package local_airpay_core
 */
class user_status {

    /** Active user — not suspended, not deleted. */
    public const ACTIVE = 'active';

    /** Marked suspended in mdl_user.suspended = 1. Can't log in. */
    public const SUSPENDED = 'suspended';

    /** Marked deleted in mdl_user.deleted = 1. Won't appear in most lists. */
    public const DELETED = 'deleted';

    /** Per-request cache: [userid => status string]. */
    private static array $cache = [];

    /**
     * Resolve a single user's status.
     *
     * @param int $userid mdl_user.id
     * @return string one of ACTIVE | SUSPENDED | DELETED
     */
    public static function get(int $userid): string {
        if ($userid <= 0) {
            return self::ACTIVE; // Caller passed garbage — fail safe.
        }
        if (isset(self::$cache[$userid])) {
            return self::$cache[$userid];
        }

        // Single-user fetch: defer to the batched method so we share its
        // logic. The cache will be populated as a side effect.
        self::warm([$userid]);
        return self::$cache[$userid] ?? self::ACTIVE;
    }

    /**
     * True if the user is currently suspended (not deleted).
     */
    public static function is_suspended(int $userid): bool {
        return self::get($userid) === self::SUSPENDED;
    }

    /**
     * Batch lookup — much cheaper than N calls to get() when rendering
     * a report row-by-row.
     *
     * @param int[] $userids
     * @return array<int,string> userid => status
     */
    public static function get_many(array $userids): array {
        $userids = array_map('intval', $userids);
        $userids = array_filter($userids, fn($id) => $id > 0);
        if (empty($userids)) {
            return [];
        }
        self::warm($userids);

        $out = [];
        foreach ($userids as $id) {
            $out[$id] = self::$cache[$id] ?? self::ACTIVE;
        }
        return $out;
    }

    /**
     * Resolve status from an already-loaded user record without hitting
     * the DB. Useful in render paths that already have $user.
     *
     * Mutates the per-request cache as a side effect — the next get()
     * call for this userid will be a hit.
     *
     * @param \stdClass $user must have ->id, ->suspended, ->deleted
     * @return string
     */
    public static function from_record(\stdClass $user): string {
        $id = (int)($user->id ?? 0);
        if ($id <= 0) {
            return self::ACTIVE;
        }
        if (!empty($user->deleted)) {
            self::$cache[$id] = self::DELETED;
            return self::DELETED;
        }
        if (!empty($user->suspended)) {
            self::$cache[$id] = self::SUSPENDED;
            return self::SUSPENDED;
        }
        self::$cache[$id] = self::ACTIVE;
        return self::ACTIVE;
    }

    /**
     * Render the inline badge HTML for a status. Returns '' for ACTIVE so
     * callers can unconditionally concat without checking.
     *
     * Output is escaped — safe to drop directly into a Mustache `{{{ ... }}}`
     * or PHP `echo`.
     *
     * @param string $status one of ACTIVE | SUSPENDED | DELETED
     * @return string HTML or empty string
     */
    public static function badge_html(string $status): string {
        if ($status === self::ACTIVE) {
            return '';
        }
        if ($status === self::SUSPENDED) {
            $label = get_string('userstatus_suspended', 'local_airpay_core');
            return '<span class="airpay-user-status-badge airpay-user-status-badge--suspended" '
                . 'title="' . s($label) . '">' . s($label) . '</span>';
        }
        if ($status === self::DELETED) {
            $label = get_string('userstatus_deleted', 'local_airpay_core');
            return '<span class="airpay-user-status-badge airpay-user-status-badge--deleted" '
                . 'title="' . s($label) . '">' . s($label) . '</span>';
        }
        return '';
    }

    /**
     * Convenience: look up + render in one call.
     *
     * @param int $userid
     * @return string HTML or empty string
     */
    public static function badge_for_user(int $userid): string {
        return self::badge_html(self::get($userid));
    }

    /**
     * Reset the per-request cache. PHPUnit only — not part of the public API.
     */
    public static function reset_cache_for_phpunit(): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('reset_cache_for_phpunit() is PHPUnit-only.');
        }
        self::$cache = [];
    }

    /**
     * Warm the cache for a batch of userids. Single DB query.
     *
     * @param int[] $userids
     */
    private static function warm(array $userids): void {
        global $DB;

        // Filter out already-cached.
        $missing = array_values(array_filter(
            $userids,
            fn($id) => !isset(self::$cache[$id])
        ));
        if (empty($missing)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'uid');
        $rows = $DB->get_records_sql(
            "SELECT id, suspended, deleted
               FROM {user}
              WHERE id $insql",
            $params
        );

        foreach ($missing as $id) {
            if (!isset($rows[$id])) {
                // User vanished (rare). Treat as deleted so callers see the
                // strikethrough badge rather than silently rendering nothing.
                self::$cache[$id] = self::DELETED;
                continue;
            }
            $r = $rows[$id];
            if (!empty($r->deleted)) {
                self::$cache[$id] = self::DELETED;
            } else if (!empty($r->suspended)) {
                self::$cache[$id] = self::SUSPENDED;
            } else {
                self::$cache[$id] = self::ACTIVE;
            }
        }
    }
}

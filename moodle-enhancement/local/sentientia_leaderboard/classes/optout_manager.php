<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy opt-out manager — consent gate for leaderboard visibility.
 *
 * Two consent regimes, gated by user_type (F-002 stabilization fix
 * 2026-05-28; full ADR-017 user_type axis will collapse this into one
 * provider call):
 *
 *   1. **EMPLOYEE** (legitimate-interest basis under GDPR Art. 6(1)(f)
 *      and India DPDP Act 2023 §7(b) — workplace learning context):
 *      ABSENCE from `local_sentientia_lb_optouts` = visible. User can
 *      opt OUT via `/user/preferences.php` toggle.
 *
 *   2. **CONSUMER** (consent basis under GDPR Art. 6(1)(a) and DPDP §7(a)
 *      — self-directed public learner, no employment context, no
 *      legitimate-interest argument): user preference
 *      `sentientia_leaderboard_consent_explicit = 1` MUST be present
 *      for visibility. Default is hidden. User toggles IN via
 *      `/local/sentientia_leaderboard/preferences.php`.
 *
 * Resolution rule for who is a consumer (interim, until ADR-017):
 *   `open_path LIKE '/77%'` (Public tenant subtree).
 *
 * Read-time semantics: `ranking_engine` + WS reads filter JOINs against
 * the union of (employee opt-outs ∪ consumers-without-consent). Both
 * sets are excluded from audience-facing payload but :viewall capability
 * holders (HR analytics) still see everyone in private aggregate views.
 *
 * @package local_sentientia_leaderboard
 */
class optout_manager {

    /**
     * User preference key for consumer consent.
     * Per-user, presence required (any value != '1' or missing = not consented).
     */
    public const CONSUMER_CONSENT_PREF = 'sentientia_leaderboard_consent_explicit';

    /**
     * Path prefix that classifies a user as "consumer" rather than
     * "employee". Until ADR-017 lands, this is the lookup. After
     * ADR-017, the resolver is `user_type_factory::for_user()`.
     */
    private const CONSUMER_OPEN_PATH_PREFIX = '/77';

    /**
     * Is the given user a consumer (no employment context)?
     *
     * Interim resolution (F-002 fix, 2026-05-28) — once
     * `local_airpay_user_type` table lands per ADR-017, this method
     * becomes a one-line lookup against that table and the open_path
     * check is the migration backfill rule, not the runtime rule.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_is_consumer(int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        $openpath = (string) $DB->get_field('user', 'open_path',
            ['id' => $userid], IGNORE_MISSING);
        if ($openpath === '') {
            // No tenant assigned (e.g. siteadmins) → treat as employee
            // (workplace context). ADR-017 will revisit this default.
            return false;
        }
        // open_path = '/77' OR '/77/...' subtree
        return $openpath === self::CONSUMER_OPEN_PATH_PREFIX
            || str_starts_with($openpath, self::CONSUMER_OPEN_PATH_PREFIX . '/');
    }

    /**
     * Does the consumer have explicit consent to appear in leaderboards?
     * For non-consumers this always returns true (consent not required).
     *
     * @param int $userid
     * @return bool
     */
    public static function consumer_has_consent(int $userid): bool {
        if ($userid <= 0) {
            return false;
        }
        if (!self::user_is_consumer($userid)) {
            return true; // Non-consumer: consent not required (employee basis).
        }
        $val = get_user_preferences(self::CONSUMER_CONSENT_PREF, '0', $userid);
        return ((string) $val) === '1';
    }

    /**
     * Set / clear consumer explicit consent. No-op for non-consumers.
     *
     * @param int $userid
     * @param bool $consented
     */
    public static function set_consumer_consent(int $userid, bool $consented): void {
        if (!self::user_is_consumer($userid)) {
            return;
        }
        set_user_preference(self::CONSUMER_CONSENT_PREF,
            $consented ? '1' : '0', $userid);
    }

    /**
     * Is the given user opted out from public listing in the given customer?
     *
     * @param int $userid
     * @param int $customerid Defaults to 1 (Airpay).
     */
    public static function is_opted_out(int $userid, int $customerid = 1): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        return $DB->record_exists('local_sentientia_lb_optouts', [
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
    }

    /**
     * Opt the user out. Idempotent — calling twice doesn't create a
     * duplicate row.
     */
    public static function opt_out(int $userid, int $customerid = 1): void {
        global $DB;
        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser');
        }
        if (self::is_opted_out($userid, $customerid)) {
            return;
        }
        $DB->insert_record('local_sentientia_lb_optouts', (object) [
            'userid'       => $userid,
            'customerid'   => $customerid,
            'timeoptedout' => time(),
        ]);
    }

    /**
     * Reverse the opt-out. Idempotent.
     */
    public static function opt_in(int $userid, int $customerid = 1): void {
        global $DB;
        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser');
        }
        $DB->delete_records('local_sentientia_lb_optouts', [
            'userid'     => $userid,
            'customerid' => $customerid,
        ]);
    }

    /**
     * Get the union of (employee opt-outs) ∪ (consumers without consent)
     * for a customer — the ranking_engine filters this set out of
     * audience-facing leaderboard payloads in bulk.
     *
     * F-002 stabilization fix (2026-05-28): added the second set.
     * Previously this method only returned explicit opt-outs, which meant
     * EVERY public learner was visible by default with no lawful basis.
     * Now consumers (open_path starting with /77) are excluded unless
     * they have explicit consent stored as a user preference.
     *
     * Performance note: the consumer-set query is keyed on `open_path`
     * which has no index. On a 2,871-user local this still runs in <50ms
     * and the result is cached for the duration of a single SSE
     * connection. If production sees larger tenants we can index
     * `mdl_user(open_path(20))` or memoize the consumer set per-request.
     *
     * @param int $customerid Defaults to 1 (Airpay).
     * @return array<int, bool> Map: userid => true
     */
    public static function opted_out_userids(int $customerid = 1): array {
        global $DB;
        $out = [];

        // Set 1: explicit employee opt-outs (existing behavior).
        $rows = $DB->get_records('local_sentientia_lb_optouts',
            ['customerid' => $customerid], '', 'id, userid');
        foreach ($rows as $r) {
            $out[(int) $r->userid] = true;
        }

        // Set 2: consumers (open_path = '/77' subtree) without explicit
        // consent. F-002 (DPDP/GDPR consent gate). Resolver pivots once
        // ADR-017 user_type table is live.
        $prefix = self::CONSUMER_OPEN_PATH_PREFIX;
        $consumer_no_consent = $DB->get_records_sql(
            "SELECT u.id
               FROM {user} u
              WHERE u.deleted = 0 AND u.suspended = 0
                AND (u.open_path = :exact OR " .
                    $DB->sql_like('u.open_path', ':subtree') . ")
                AND NOT EXISTS (
                  SELECT 1 FROM {user_preferences} p
                   WHERE p.userid = u.id
                     AND p.name = :prefname
                     AND p.value = '1')",
            [
                'exact'    => $prefix,
                'subtree'  => $prefix . '/%',
                'prefname' => self::CONSUMER_CONSENT_PREF,
            ]);
        foreach ($consumer_no_consent as $r) {
            $out[(int) $r->id] = true;
        }
        return $out;
    }

    /**
     * Render the value for the /user/preferences.php preference (returns
     * '1' if opted out, '0' otherwise).
     *
     * Used by core_user\output\myprofile or the preferences page renderer
     * — wired up via lib.php callback.
     */
    public static function get_preference_value(int $userid, int $customerid = 1): string {
        return self::is_opted_out($userid, $customerid) ? '1' : '0';
    }

    /**
     * Apply a preference change submitted from /user/preferences.php.
     * $value === '1' or true → opt out. Anything else → opt in.
     */
    public static function set_preference_value(int $userid, $value,
                                                  int $customerid = 1): void {
        $opt_out = ($value === '1' || $value === 1 || $value === true);
        if ($opt_out) {
            self::opt_out($userid, $customerid);
        } else {
            self::opt_in($userid, $customerid);
        }
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-tenant Sentientia Live kill switches - the write behind
 * admin/tenant_switches.php (B18 / F-089).
 *
 * Until 2026-09-25 the page could not flip anything. It read flag_key with
 * PARAM_ALPHANUMEXT . '.', a parameter type that does not exist, so
 * required_param() threw on every flip before the whitelist ran. Past that,
 * it upserted local_sentientia_feature_flags by hand (no audit row) and then
 * called feature_flags::invalidate_cache(), which does not exist either
 * (the method is invalidate_caches()). Its whitelist also named
 * live.questiontype.scale, which is not registered, and left out
 * live.questiontype.ranking, which is.
 *
 * The write now goes through feature_flags::set(): the registry check, the
 * customer-layer guard, the audit row and the cache invalidation all come
 * with it. One flip writes exactly one (customer, tenant) override and
 * nothing else, so switching Live off for one tenant leaves every other
 * tenant, and the global default, as they were.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_switches {

    /** Every live.* flag registered in db/feature_flags.php - all this page may touch. */
    public const FLAGS = [
        'live.enabled',
        'live.realtime.enabled',
        'live.allow_anonymous',
        'live.questiontype.multichoice',
        'live.questiontype.openended',
        'live.questiontype.wordcloud',
        'live.questiontype.quiz',
        'live.questiontype.rating',
        'live.questiontype.ranking',
    ];

    /** The audit reason recorded against every flip from this page. */
    public const AUDIT_REASON = 'live tenant_switches';

    /**
     * Set one Live flag for one (customer, tenant) scope.
     *
     * Site configuration only, as the page is. tenant 0 means customer-wide;
     * customer 0 means the global row. feature_flags::set() refuses a
     * customer-scoped write while the customer layer is off.
     *
     * @param string $flagkey one of self::FLAGS
     * @param int $customerid 0 or a customer id
     * @param int $tenantid 0 or a tenant root
     * @param bool $enabled the new value
     * @throws \required_capability_exception without moodle/site:config
     * @throws \moodle_exception invalidflag for a key outside self::FLAGS, or
     *         whatever feature_flags::set() refuses
     */
    public static function flip(string $flagkey, int $customerid, int $tenantid, bool $enabled): void {
        global $USER;
        require_capability('moodle/site:config', \context_system::instance());
        if (!in_array($flagkey, self::FLAGS, true)) {
            throw new \moodle_exception('invalidflag', 'local_sentientia_live');
        }
        if ($customerid < 0 || $tenantid < 0) {
            throw new \invalid_parameter_exception('customer_id and tenant_id must be 0 or positive');
        }
        \local_sentientia_platform\feature_flags::set($flagkey, $tenantid, $enabled,
            (int) $USER->id, self::AUDIT_REASON, $customerid);
    }
}

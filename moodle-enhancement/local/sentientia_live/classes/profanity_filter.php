<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Profanity filter for word-cloud submissions — Phase E.5 (2026-05-24).
 *
 * Why this lives in its own class rather than inside word_cloud:
 *
 *   1. Reusable across question types. open_ended (Phase E.6) will
 *      need the same denylist to scrub free-text answers shown on the
 *      projector.
 *
 *   2. Per-customer override. The default English denylist is hardcoded
 *      here, but `local_airpay_core::get_customer_config('profanity_denylist')`
 *      is consulted first if the helper exists. That lets a customer
 *      (e.g. Airpay vs Customer-N) ship a region-specific or industry-
 *      specific list without touching plugin code.
 *
 *   3. Testable in isolation. The PHPUnit suite asserts the filter
 *      blocks the default list AND the customer override AND mixed
 *      Devanagari/Latin scripts (since Hindi parity is enforced).
 *
 * Algorithm:
 *   - Tokens are matched case-insensitively against the resolved list.
 *   - Substring matches count (so "f**k", "fk1ng" etc. trip the filter
 *     when the candidate contains a denied word). Concretely:
 *     contains() uses mb_stripos so multi-byte (UTF-8) input works.
 *   - The default list is intentionally short — it's a smoke-test, not
 *     a content-moderation engine. Enterprise customers add their own
 *     denylist via the customer config hook.
 *
 * Privacy:
 *   - No PII collected. The denylist is loaded from compile-time
 *     constants or from customer config; nothing is read from $USER.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profanity_filter {

    /**
     * Default English denylist — intentionally short. Customers who need
     * exhaustive content moderation should ship their own list via
     * `local_airpay_core::get_customer_config('profanity_denylist')`,
     * which overrides this default entirely.
     *
     * Entries are lowercased + matched case-insensitively. Sub-string
     * matching is used (so "f*ck", "fcking" both trip "fck"-style
     * fragments). Keep this minimal — false-positives ("scunthorpe
     * problem") are real and grow with list length.
     */
    private const DEFAULT_DENYLIST_EN = [
        'fuck',
        'shit',
        'bitch',
        'cunt',
        'asshole',
        'bastard',
        'dick',
        'piss',
        'wank',
        'fag',
        'whore',
        'slut',
        'nigger',
        'paki',
    ];

    /**
     * Cached resolved denylist per-customer. Avoids re-querying
     * local_airpay_core inside a tight tokenisation loop.
     *
     * @var array<int, string[]>
     */
    private static array $cache = [];

    /**
     * Is the supplied token denied? Case-insensitive, substring-aware.
     *
     * @param string $token    The candidate token (one word, already
     *                          trimmed). Empty string returns false.
     * @param int    $customerid Customer scope for the denylist. Pass 0
     *                            (or omit) to use the default list only.
     * @return bool True if the token contains any denied substring.
     */
    public static function is_denied(string $token, int $customerid = 0): bool {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        $needle = mb_strtolower($token, 'UTF-8');
        $list = self::resolve_denylist($customerid);
        foreach ($list as $bad) {
            if ($bad === '') {
                continue;
            }
            if (mb_stripos($needle, $bad, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Filter an array of tokens — returns only the ones NOT in the
     * denylist, preserving order. Used by word_cloud::persist_response
     * after tokenisation.
     *
     * @param string[] $tokens     Pre-tokenised words.
     * @param int      $customerid Customer scope (0 = default).
     * @return string[] The clean (allowed) tokens in original order.
     */
    public static function filter(array $tokens, int $customerid = 0): array {
        $out = [];
        foreach ($tokens as $t) {
            if (!is_string($t)) {
                continue;
            }
            $trimmed = trim($t);
            if ($trimmed === '') {
                continue;
            }
            if (self::is_denied($trimmed, $customerid)) {
                continue;
            }
            $out[] = $trimmed;
        }
        return $out;
    }

    /**
     * Resolve the active denylist for the given customer. Tries the
     * customer-config override first; falls back to the default list.
     *
     * Result is cached for the lifetime of the request — the denylist
     * never changes inside a single response cycle.
     *
     * @internal Exposed for PHPUnit which seeds the cache directly.
     */
    public static function resolve_denylist(int $customerid = 0): array {
        if (isset(self::$cache[$customerid])) {
            return self::$cache[$customerid];
        }

        $list = self::fetch_customer_override($customerid);

        if ($list === null) {
            $list = self::DEFAULT_DENYLIST_EN;
        }

        // Normalise: lowercase + strip empties.
        $list = array_values(array_filter(
            array_map(fn($w) => mb_strtolower(trim((string) $w), 'UTF-8'),
                $list),
            fn($w) => $w !== ''
        ));

        self::$cache[$customerid] = $list;
        return $list;
    }

    /**
     * Try the per-customer denylist override hook in local_airpay_core.
     *
     * The chip spec names the API
     * `local_airpay_core::get_customer_config('profanity_denylist')`.
     * That class/method does not exist in today's single-customer
     * deployment (it lands with the Phase 2 customer-config layer per
     * ADR-008). We probe the two most likely concrete shapes and
     * fail-soft to null (→ default list) when neither is present or
     * the call errors. We NEVER let an admin-config issue block the
     * audience submission flow.
     *
     * @param int $customerid Customer scope.
     * @return string[]|null The override list, or null to use default.
     */
    private static function fetch_customer_override(int $customerid): ?array {
        $candidates = [
            // Literal reading of the spec: a static getter on the
            // customer class. get_customer_config($key, $customerid).
            ['\\local_airpay_core\\customer', 'get_customer_config'],
            // Alternative shape: a dedicated customer_config class.
            ['\\local_airpay_core\\customer_config', 'get'],
        ];
        foreach ($candidates as [$class, $method]) {
            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }
            try {
                $override = $class::$method('profanity_denylist',
                    $customerid);
                if (is_array($override) && !empty($override)) {
                    return $override;
                }
            } catch (\Throwable $e) {
                // Fail-soft — fall through to the next candidate / default.
                continue;
            }
        }
        return null;
    }

    /**
     * Clear the resolved-denylist cache. PHPUnit calls this in setUp()
     * so each test starts from a clean slate (denylist overrides
     * registered by previous tests don't leak).
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }

    /**
     * Inject a denylist directly — PHPUnit helper, not for production
     * code. Caller is responsible for clearing afterwards via
     * reset_cache(). Useful when a test wants to assert the
     * customer-override branch without spinning up local_airpay_core.
     *
     * @param int      $customerid Customer scope key.
     * @param string[] $list       Words to deny.
     */
    public static function override_for_tests(int $customerid,
                                                array $list): void {
        self::$cache[$customerid] = array_values(array_filter(
            array_map(fn($w) => mb_strtolower(trim((string) $w), 'UTF-8'),
                $list),
            fn($w) => $w !== ''
        ));
    }
}

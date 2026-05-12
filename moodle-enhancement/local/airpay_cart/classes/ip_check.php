<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * IP / CIDR matcher for the gateway callback allow-list.
 *
 * Spun out from callback.php so it can be unit-tested in isolation.
 * Supports v4 single addresses ("203.0.113.42") and CIDR ranges
 * ("203.0.113.0/24"). v6 is supported via PHP's inet_pton + binary
 * compare.
 *
 * Phase 8.1: introduced to back B11 (callback DoS / no rate limit).
 */
class ip_check {

    /**
     * Does `$ip` fall inside `$cidr`?
     *
     * Examples:
     *   ip_in_cidr('203.0.113.42', '203.0.113.0/24')  → true
     *   ip_in_cidr('203.0.113.42', '203.0.113.42')    → true (single)
     *   ip_in_cidr('10.0.0.1',     '203.0.113.0/24')  → false
     *   ip_in_cidr('2001:db8::1',  '2001:db8::/32')   → true
     */
    public static function ip_in_cidr(string $ip, string $cidr): bool {
        $ip   = trim($ip);
        $cidr = trim($cidr);
        if ($ip === '' || $cidr === '') {
            return false;
        }
        // Single-address case.
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if ($bits < 0) {
            return false;
        }

        $ip_bin     = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        if (strlen($ip_bin) !== strlen($subnet_bin)) {
            return false;  // mixing v4/v6
        }

        // Phase 8.1 re-audit N3 fix — reject prefix > address-family width.
        // Before this: /33+ on v4 would silently match because $ip_bin[4]
        // is out of bounds → ord('') === 0 → both sides 0 → always equal.
        $max_bits = strlen($ip_bin) * 8;  // 32 for v4, 128 for v6
        if ($bits > $max_bits) {
            return false;
        }

        // Compare first $bits bits.
        $byte_count = intdiv($bits, 8);
        $bit_rem    = $bits % 8;

        if ($byte_count > 0
                && substr($ip_bin, 0, $byte_count) !== substr($subnet_bin, 0, $byte_count)) {
            return false;
        }
        if ($bit_rem === 0) {
            return true;
        }
        $mask = chr(0xff << (8 - $bit_rem) & 0xff);
        return (ord($ip_bin[$byte_count]) & ord($mask))
             === (ord($subnet_bin[$byte_count]) & ord($mask));
    }
}

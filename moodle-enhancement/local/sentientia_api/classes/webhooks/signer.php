<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\webhooks;

defined('MOODLE_INTERNAL') || die();

/**
 * HMAC-SHA256 request signing for outbound webhooks (ADR-030 Wave A).
 *
 * Header format (Stripe-style, timestamped to bound replay):
 *   X-Sentientia-Signature: t=<unix>,v1=<hex hmac_sha256(t "." body, secret)>
 *
 * Consumers recompute the HMAC over "<t>.<raw body>" with their subscription
 * secret, compare in constant time, and reject if |now - t| exceeds their
 * tolerance (we document 300 s). verify() is the reference implementation
 * consumers can copy, and what our tests use.
 *
 * @package local_sentientia_api
 */
class signer {

    /** @var string HTTP header carrying the signature. */
    public const HEADER = 'X-Sentientia-Signature';

    /** @var int Default replay tolerance in seconds. */
    public const TOLERANCE = 300;

    /**
     * Produce the signature header value for a body.
     *
     * @param string   $body      Raw request body exactly as sent
     * @param string   $secret    Subscription secret
     * @param int|null $timestamp Unix time (defaults to now)
     * @return string  "t=<ts>,v1=<hex>"
     */
    public static function sign(string $body, string $secret, ?int $timestamp = null): string {
        $t = $timestamp ?? time();
        $mac = hash_hmac('sha256', $t . '.' . $body, $secret);
        return 't=' . $t . ',v1=' . $mac;
    }

    /**
     * Verify a signature header against a body + secret.
     *
     * @param string   $body
     * @param string   $secret
     * @param string   $header    The X-Sentientia-Signature value
     * @param int      $tolerance Max |now - t| in seconds
     * @param int|null $now       Injected clock for tests
     * @return bool
     */
    public static function verify(string $body, string $secret, string $header,
                                  int $tolerance = self::TOLERANCE, ?int $now = null): bool {
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            $kv = trim($kv);
            if ($kv === '' || strpos($kv, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $kv, 2);
            $parts[$k] = $v;
        }
        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            return false;
        }
        $t = (int) $parts['t'];
        $clock = $now ?? time();
        if (abs($clock - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $body, $secret);
        return hash_equals($expected, $parts['v1']);
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Structured logger for the airpay_* plugin family.
 *
 * Per `docs/SUPP-H-OBSERVABILITY-PLAYBOOK-2026-05-12.md` Section 5,
 * every diagnostic log emitted by an airpay plugin should follow a
 * common JSON-shaped structure so logs can be searched by field and
 * joined across plugins inside an APM tool.
 *
 * Usage:
 *
 *     \local_airpay_core\structured_logger::info('cart',
 *         'checkout_completed',
 *         ['orderid' => 4242, 'duration_ms' => 245]);
 *
 *     \local_airpay_core\structured_logger::error('proctoring',
 *         'aws_unreachable',
 *         ['attempt' => 3, 'http' => 503]);
 *
 * Output structure (single-line JSON when running on production
 * with APM, indented when CLI):
 *
 *     {
 *       "timestamp":   "2026-05-12T19:45:23.123Z",
 *       "level":       "info",
 *       "component":   "local_airpay_cart",
 *       "event":       "checkout_completed",
 *       "userid":      12345,
 *       "tenant":      77,
 *       "request_id":  "abc123def456",
 *       "duration_ms": 245,
 *       "extra":       { "orderid": 4242 }
 *     }
 *
 * The logger emits to Moodle's `debugging()` channel by default. When
 * the APM agent is installed (see SUPP-H Section 2), the same call
 * also sends a custom event to the APM tool's collector.
 *
 * Safety: NEVER include raw user-supplied content (names, emails,
 * passwords) in `$extra`. Use opaque identifiers (userid, orderid).
 * The structured logger explicitly does NOT redact these fields.
 *
 * @package local_airpay_core
 */
class structured_logger {

    /**
     * Per-request id, generated lazily on first use. Lets us join
     * multiple log entries that belong to the same HTTP request.
     */
    private static ?string $request_id = null;

    /**
     * Log levels recognised by this logger.
     */
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warn';
    public const LEVEL_ERROR = 'error';

    public static function debug(string $plugin, string $event, array $extra = []): void {
        self::emit(self::LEVEL_DEBUG, $plugin, $event, $extra);
    }

    public static function info(string $plugin, string $event, array $extra = []): void {
        self::emit(self::LEVEL_INFO, $plugin, $event, $extra);
    }

    public static function warn(string $plugin, string $event, array $extra = []): void {
        self::emit(self::LEVEL_WARN, $plugin, $event, $extra);
    }

    public static function error(string $plugin, string $event, array $extra = []): void {
        self::emit(self::LEVEL_ERROR, $plugin, $event, $extra);
    }

    /**
     * Internal: build the structured row + emit.
     */
    private static function emit(string $level, string $plugin,
                                  string $event, array $extra): void {
        global $USER;

        $row = [
            'timestamp' => self::timestamp(),
            'level'     => $level,
            'component' => 'local_airpay_' . $plugin,
            'event'     => $event,
            'userid'    => isset($USER->id) ? (int) $USER->id : 0,
            'tenant'    => empty($USER->id) ? 0 : tenant::root_for_current_user(),
            'request_id' => self::request_id(),
            'extra'     => self::scrub_extra($extra),
        ];

        // Promote a few common `extra` fields to top-level for easier
        // APM tool indexing — duration_ms is hot enough to warrant.
        if (isset($extra['duration_ms'])) {
            $row['duration_ms'] = (int) $extra['duration_ms'];
        }

        $line = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = '{"level":"error","event":"structured_logger_encode_failed"}';
        }

        // Channel 1: Moodle debugging (visible in error log when
        // $CFG->debug is set).
        if ($level === self::LEVEL_ERROR || $level === self::LEVEL_WARN) {
            debugging($line, DEBUG_NORMAL);
        } else {
            debugging($line, DEBUG_DEVELOPER);
        }

        // Channel 2: APM custom event if the agent is present. Hook is
        // a guarded function-exists check so the logger remains usable
        // without the agent installed.
        if (function_exists('newrelic_record_custom_event')) {
            \newrelic_record_custom_event('airpay_event', $row);
        }
    }

    /**
     * ISO-8601 timestamp with millisecond precision in UTC.
     */
    private static function timestamp(): string {
        $micro = microtime(true);
        $sec   = (int) floor($micro);
        $msec  = (int) (($micro - $sec) * 1000);
        return gmdate('Y-m-d\TH:i:s', $sec) . sprintf('.%03dZ', $msec);
    }

    /**
     * Per-request id. Lazy-generated on first call.
     */
    private static function request_id(): string {
        if (self::$request_id === null) {
            // Prefer the trace id from any APM agent or load-balancer
            // header if present (e.g. X-Request-Id, X-Amzn-Trace-Id).
            $candidates = [
                $_SERVER['HTTP_X_REQUEST_ID'] ?? '',
                $_SERVER['HTTP_X_AMZN_TRACE_ID'] ?? '',
                $_SERVER['HTTP_X_NEWRELIC_ID'] ?? '',
            ];
            foreach ($candidates as $c) {
                if ($c !== '') {
                    self::$request_id = preg_replace('/[^a-zA-Z0-9_-]/', '',
                        substr($c, 0, 64));
                    return self::$request_id;
                }
            }
            // No upstream id — generate our own.
            self::$request_id = bin2hex(random_bytes(8));
        }
        return self::$request_id;
    }

    /**
     * Defensive PII scrub on the `extra` dict.
     *
     * The contract of this logger says the CALLER is responsible for
     * not putting PII in `extra`. This layer is the belt-and-braces
     * defence: if a known PII field name appears, redact its value.
     */
    private static function scrub_extra(array $extra): array {
        $banned = [
            'password', 'pass', 'pwd', 'secret', 'token', 'api_key',
            'apikey', 'authorization', 'auth', 'card_number', 'cvv',
            'email', 'phone', 'address', 'firstname', 'lastname',
            'fullname', 'idnumber',
        ];
        foreach ($extra as $k => $v) {
            $lk = strtolower((string) $k);
            foreach ($banned as $b) {
                if (str_contains($lk, $b)) {
                    $extra[$k] = '(redacted)';
                    break;
                }
            }
        }
        return $extra;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\lrs;

defined('MOODLE_INTERNAL') || die();

/**
 * Thrown by {@see rate_limiter::check_and_increment()} when a client has
 * exceeded its budget for the current window.
 *
 * H3 fix — UAT-SECURITY-POSTURE-2026-09-03. Carries the number of
 * seconds until the caller should retry so lrs/statements.php can emit
 * an HTTP 429 with a `Retry-After` header, mirroring the SCIM endpoint's
 * 429 handling in local_sentientia_api.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limit_exceeded extends \moodle_exception {

    /** @var int Seconds until the current fixed window resets. */
    public int $retryafter;

    /**
     * @param int $budget     The budget that was exceeded (max requests per window).
     * @param int $retryafter Seconds until the window resets. Always >= 1.
     */
    public function __construct(int $budget, int $retryafter) {
        $this->retryafter = max(1, $retryafter);
        parent::__construct('error_lrs_ratelimited', 'local_sentientia_xapi', '', $budget);
    }
}

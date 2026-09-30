<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * A CLI guard refused the operation (exit code 3). The message names the
 * missing condition.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guard_refused extends bizlms_exception {

    /**
     * @return int
     */
    public function exitcode(): int {
        return 3;
    }
}

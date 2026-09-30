<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class of every failure the BizLMS import framework raises on purpose.
 *
 * Messages carry ids and codes only: they end up in the legacystep error
 * column and in reports, and both leave the database (ADR-032 privacy rules).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bizlms_exception extends \RuntimeException {

    /**
     * CLI exit code for this failure (ADR-032: 1 = blocker, failure, collision
     * or source drift; 3 = a guard refused).
     *
     * @return int
     */
    public function exitcode(): int {
        return 1;
    }
}

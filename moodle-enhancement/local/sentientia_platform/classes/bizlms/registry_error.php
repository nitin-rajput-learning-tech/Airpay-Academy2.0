<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The importer registry is invalid: a duplicated feature key, a legacy table
 * claimed or declined twice, an unknown or cyclic dependency, a class that does
 * not implement importer, a plugin below requires_version(), or a step that
 * breaks the id-policy rule.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry_error extends bizlms_exception {

    /** @var string[] Every problem found, one line each. */
    public array $problems;

    /**
     * @param string[] $problems
     */
    public function __construct(array $problems) {
        $this->problems = array_values($problems);
        parent::__construct('BizLMS importer registry is invalid: ' . implode('; ', $this->problems));
    }
}

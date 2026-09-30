<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * One owner choice an importer needs (ADR-032 decision 9: owner choices are
 * data, not defaults).
 *
 * The value comes from the checked-in decisions file. A required decision that
 * is absent and has no default blocks the feature.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision {

    /**
     * @param string $key Key in the decisions file, for example classroom.gender_map.
     * @param string $description What is being decided, for the preflight report.
     * @param bool $required A missing value with no default blocks the feature.
     * @param mixed $default Value used when the file does not carry the key.
     *        A default is itself an owner choice, so use it sparingly.
     * @param array|null $allowed When set, the only values the file may carry.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $description,
        public readonly bool $required = true,
        public readonly mixed $default = null,
        public readonly ?array $allowed = null,
    ) {
    }
}

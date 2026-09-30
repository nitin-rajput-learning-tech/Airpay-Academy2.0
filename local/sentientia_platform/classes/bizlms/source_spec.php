<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * One legacy table an importer claims (ADR-032, "Importer interface").
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_spec {

    /**
     * @param string $table Legacy table name without prefix.
     * @param bool $required A missing required table is a blocker; when every
     *        claimed table is missing the feature is not_applicable.
     * @param array<string, array<string|int, string>> $enums column => value =>
     *        meaning. Preflight prints a histogram of each column; a value that
     *        is not a key here blocks the feature until the decisions file maps it.
     * @param string[] $optionalcolumns Production-only columns (for example
     *        costcenterid) that a snapshot install file does not declare. A step
     *        may name them; they are dropped from the read when absent.
     */
    public function __construct(
        public readonly string $table,
        public readonly bool $required = true,
        public readonly array $enums = [],
        public readonly array $optionalcolumns = [],
    ) {
    }
}

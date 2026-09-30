<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The writer refused a write (ADR-032, "Writing rules"): an undeclared table, an
 * unknown field, an overlong char, a missing NOT NULL value or timestamp, a
 * non-integer for an int column, or a write during a dry run.
 *
 * The message names the table and column, never the value.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class writer_refused extends bizlms_exception {
}

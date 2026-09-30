<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * A legacy source changed between runs, or mutated after import. The source is
 * frozen after Phase 0, so a change is a blocker to investigate, not to import
 * (ADR-032, "--incremental rejected").
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_drift extends bizlms_exception {
}

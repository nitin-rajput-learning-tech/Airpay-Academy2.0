<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The side-effect tripwire saw a write outside the declared targets and reviewed
 * core writes (ADR-032, "Side-effect safety" 3). The run aborts before the next
 * feature.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tripwire_tripped extends bizlms_exception {
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for enrol_sentientiasub.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_sentientiasub_upgrade(int $oldversion): bool {
    // No upgrade steps yet — the install.xml is authoritative for v0.1.0.
    return true;
}

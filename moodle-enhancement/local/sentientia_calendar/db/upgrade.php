<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_calendar.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_calendar_upgrade(int $oldversion): bool {
    // Tier 2.6 Phase 1 install — nothing to migrate from yet.
    return true;
}

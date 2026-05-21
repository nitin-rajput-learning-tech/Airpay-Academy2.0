<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_live.
 *
 * Phase E.0 ships the initial schema via db/install.xml — this file
 * starts as a no-op savepoint and grows as subsequent phases ship
 * schema changes.
 *
 * @package local_sentientia_live
 */
function xmldb_local_sentientia_live_upgrade(int $oldversion): bool {
    // No upgrade steps yet — this is Phase E.0, the first version.
    // The install.xml is loaded by Moodle's core install routine on
    // first install. Future phases land their schema changes below
    // with savepoints.

    return true;
}

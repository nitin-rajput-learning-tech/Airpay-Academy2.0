<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_leaderboard.
 *
 * Phase L.0 ships the initial schema via db/install.xml — this file
 * starts as a no-op savepoint and grows as subsequent phases ship
 * schema changes.
 *
 * @package local_sentientia_leaderboard
 */
function xmldb_local_sentientia_leaderboard_upgrade(int $oldversion): bool {
    // No upgrade steps yet — this is Phase L.0, the first version.
    return true;
}

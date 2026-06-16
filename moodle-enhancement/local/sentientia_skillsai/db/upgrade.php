<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_skillsai.
 *
 * P0.1.0 is the initial install — there are no upgrade steps yet. The
 * function exists (and returns true) so the plugin upgrade machinery is
 * wired from day one; subsequent phases add versioned `if ($oldversion <
 * NNNN)` blocks here following the template in .claude/rules/database.md.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_sentientia_skillsai_upgrade(int $oldversion): bool {
    // No upgrade steps yet — install.xml defines the full P0.1.0 schema.
    // Future phases add `if ($oldversion < 2026XXXXNN) { ... savepoint }`.
    return true;
}

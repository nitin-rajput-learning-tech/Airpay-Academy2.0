<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_ratings.
 *
 * Added at the ADR-022 batch-1 rename (local_airpay_ratings -> local_sentientia_ratings,
 * 2026-06-03). The rename itself is a DB hand-over (table / config_plugins / capability +
 * component / role-assignment / files re-point) performed out-of-band by
 * tools/rename/handover.php; this no-op upgrade step exists so that the version bump in
 * version.php drives Moodle's standard upgrade flow to rebuild the component classmap and
 * re-register the renamed web service from db/services.php.
 *
 * @package local_sentientia_ratings
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_ratings_upgrade(int $oldversion): bool {
    // No schema changes — the rename hand-over is complete before this runs.
    // Bump-only: triggers classmap rebuild + WS re-registration via the standard path.
    return true;
}

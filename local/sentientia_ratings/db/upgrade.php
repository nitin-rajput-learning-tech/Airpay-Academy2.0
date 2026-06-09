<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_ratings.
 *
 * Added at the ADR-022 batch-1 rename (local_airpay_ratings -> local_sentientia_ratings,
 * 2026-06-03). The rename itself was a DB hand-over (table/config/capability/role-assignment
 * re-point) performed out-of-band; this version bump exists so Moodle's upgrade flow rebuilds
 * the component classmap + re-registers the renamed web service cleanly.
 *
 * @package local_sentientia_ratings
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_ratings_upgrade(int $oldversion): bool {
    // No schema changes — the rename hand-over is complete. Bump-only to trigger
    // classmap rebuild + WS re-registration via the standard upgrade path.
    return true;
}

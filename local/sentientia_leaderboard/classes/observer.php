<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer — Phase L.1 (2026-05-24).
 *
 * Listens for {@see \local_sentientia_leaderboard\event\rankings_updated},
 * which is fired by the ranking engine after a wholesale recompute commits.
 *
 * Behaviour matrix:
 *   - master leaderboard flag OFF  → no-op (the recompute never runs)
 *   - notification flag OFF        → silent no-op here
 *   - notification flag ON         → delegate to message_helper::dispatch()
 *
 * Like every other airpay_* observer the handler is wrapped in a
 * try/catch so a failure here cannot break the originating recompute
 * flow — Moodle's observer dispatcher already catches exceptions, but
 * the explicit wrapper keeps debugging() readable.
 *
 * @package local_sentientia_leaderboard
 */
class observer {

    /**
     * Handle a `rankings_updated` event by routing the carried `changes`
     * payload through {@see message_helper::dispatch()}.
     */
    public static function on_rankings_updated(
            \local_sentientia_leaderboard\event\rankings_updated $event): void {
        try {
            // Gate on the L.1 master flag — default OFF. When sentientia_platform
            // isn't installed, treat as OFF (fail-safe).
            if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
                return;
            }
            if (!\local_sentientia_platform\feature_flags::is_enabled(
                    message_helper::FLAG_KEY)) {
                return;
            }
            $boardid = (int) $event->objectid;
            if ($boardid <= 0) {
                return;
            }
            $changes = [];
            if (isset($event->other) && is_array($event->other)
                    && isset($event->other['changes'])
                    && is_array($event->other['changes'])) {
                $changes = $event->other['changes'];
            }
            if (empty($changes)) {
                return;
            }
            message_helper::dispatch($boardid, $changes);
        } catch (\Throwable $e) {
            debugging('local_sentientia_leaderboard observer failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

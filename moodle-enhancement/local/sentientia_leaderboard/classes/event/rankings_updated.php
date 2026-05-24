<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired by {@see \local_sentientia_leaderboard\ranking_engine::recompute()}
 * after a board's entries have been wholesale-replaced and the transaction
 * has committed.
 *
 * Phase L.1 (2026-05-24): exists so {@see \local_sentientia_leaderboard\observer}
 * can react to "interesting" rank shifts (>=5 positions or a fresh top-10
 * entry) and route Moodle messages to the affected learners. The event
 * is intentionally a different channel from the SSE-pump
 * `leaderboard.recomputed` row in {local_sentientia_lb_events} —
 * SSE drives the live UI, this event drives messaging. Keeping them
 * separate means one can be toggled OFF without disturbing the other.
 *
 * Payload contract — `other`:
 *   - changes : list<{userid:int, old_rank:int, new_rank:int, reason:string}>
 *               where reason is 'top10_entry' or 'large_move'.
 *               `old_rank` = 0 when the user was not previously ranked.
 *
 * The recompute path caps `changes` at 500 entries to keep `other`
 * compact — the throttle table downstream ensures one message per
 * learner per board per 24h, so larger payloads add no value.
 *
 * @package local_sentientia_leaderboard
 */
class rankings_updated extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_lb_boards';
    }

    public static function get_name(): string {
        return get_string('event_rankings_updated',
            'local_sentientia_leaderboard');
    }

    public function get_description(): string {
        $changecount = isset($this->other['changes'])
            ? count((array) $this->other['changes'])
            : 0;
        return "Board {$this->objectid} re-ranked; {$changecount} notable change(s).";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_leaderboard/view.php',
            ['id' => $this->objectid]);
    }
}

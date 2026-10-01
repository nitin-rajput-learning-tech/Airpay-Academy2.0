<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * Every legacy reset that the standard log recorded becomes one history row, at the time it happened.
 *
 * The legacy cron fired \local_recompletion\event\completion_reset once per reset, so each log row of that
 * event is the real evidence of a reset: who, which course, when. The log is a live core table, not a legacy
 * table, and the rows are a filtered subset of it, so the accounting unit is a derived group of one row (the
 * framework does not require every row of a live table to have a map row; verify() counts the events itself).
 *
 * A reset that ended an archived cycle takes its previous completion from that cycle. A reset with no archived
 * cycle takes it from the latest course_completed log row before it, else leaves it empty. When an earlier
 * run already gave that cycle an inferred history row (its reset was missing from the log then), the event is
 * folded into it and the inferred_resets recompute step gives the row the real time.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events_step extends step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.events';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::EVENT_UNIT;
    }

    /**
     * {@inheritdoc}
     */
    public function targettable(): string {
        return sources::HISTORY;
    }

    /**
     * {@inheritdoc}
     */
    public function group_by(): array {
        return ['id'];
    }

    /**
     * {@inheritdoc}
     */
    public function columns(): array {
        return ['relateduserid', 'courseid', 'userid', 'timecreated', 'origin'];
    }

    /**
     * {@inheritdoc}
     */
    public function source_filter(): array {
        return sources::reset_filter();
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $row = reset($rows);
        $id = (int) $row->id;
        $user = (int) $row->relateduserid;
        $course = (int) $row->courseid;
        $time = (int) $row->timecreated;

        if ($user <= 0 || $course <= 0) {
            return [outcome::skip($id, 'incomplete_event', 'missing_subject')];
        }
        if (!$ctx->lookups->user_exists($user)) {
            return [outcome::skip($id, 'orphan_user', 'user_not_found')];
        }

        $evidence = evidence::of($ctx);
        $pair = $evidence->pairing($user, $course);
        $ccid = $pair['event'][$id] ?? null;

        if ($ccid !== null) {
            // An earlier run may have given this cycle a history row with an inferred time.
            $inferred = $ctx->map->resolve(sources::CC, $ccid, 'history');
            if ($inferred !== null) {
                return [outcome::fold($id, sources::HISTORY, $inferred, 'matched_inferred')];
            }
        }

        $completion = $ccid === null ? null : $evidence->completion_row($user, $course, $ccid);
        if ($completion !== null) {
            $previous = $completion['completed'] > 0 ? $completion['completed'] : null;
        } else {
            $previous = $evidence->completed_before($user, $course, $time);
        }

        [$reason, $resetby, $unclear] = mapper::reset_source($row->origin, (int) $row->userid, $user);
        $config = $evidence->config($course);
        $attempted = $evidence->attempted_between($user, $course, $evidence->reset_before($user, $course, $time), $time);

        $history = (object) [
            'ruleid' => (int) ($ctx->map->resolve(sources::RULE_UNIT, $course) ?? 0),
            'userid' => $user,
            'courseid' => $course,
            'reason' => $reason,
            'reset_by_userid' => $resetby,
            'previous_timecompleted' => $previous,
            'reset_grades' => mapper::switch_on($config['deletegradedata'] ?? null),
            'reset_attempts' => $attempted ? 1 : mapper::switch_on($config['quiz'] ?? null),
            'dryrun' => 0,
            'timecreated' => $time,
            'source' => sources::LEGACY,
            'time_inferred' => 0,
        ];
        $outcome = outcome::insert($id, sources::HISTORY, $history);
        if ($unclear) {
            $outcome->warn('reset_source_unclear');
        }
        return [$outcome];
    }
}

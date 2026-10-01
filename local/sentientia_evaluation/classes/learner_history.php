<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * A learner's own evaluation history (ADR-032, mapping doc section 18, "Code fixes" 6).
 *
 * BizLMS showed a learner the evaluations they had completed on their dashboard; after the BizLMS import that
 * history is in Sentientia's tables, and this is the one read that gives it back to the person it belongs to. It
 * reads only rows that name the learner, and shows nothing about anyone else.
 *
 * What it can and cannot show. A named response is the learner's own (userid). An answer to an anonymous form is
 * stored with user id 0 and never reaches this page; what the learner sees for such a form is the assignment row
 * that says they responded, with the day and not the minute (evaluation_manager::submitted_label()), and a note
 * that their answers are not linked to them. An assignment with no response is listed as waiting or closed.
 *
 * Shown behind the default-OFF flag sentientia.evaluation.learner_history (see my_evaluations.php).
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learner_history {

    /** Status values, as local_sentientia_evaluation_assign.status stores them. */
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_EXPIRED = 'expired';

    /**
     * The evaluations one learner answered, was asked to answer, or was asked to answer and missed.
     *
     * One row per evaluation, the newest first. A learner who was assigned a form twice, or who answered a
     * repeatable form three times, still has one row for it.
     *
     * @param int $userid
     * @return \stdClass[] Each: evaluationid, name (raw; the caller formats it), status (a STATUS_ value),
     *         time (Unix time, 0 when unknown), anonymous (bool: the learner's answers are not linked to them),
     *         imported (bool: the form came from the previous system).
     */
    public static function for_user(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        $rows = [];

        $assignments = $DB->get_records_sql(
            "SELECT a.id, a.evaluationid, a.status, a.due_at, a.responded_at, a.timecreated, e.name, e.anonymous
               FROM {local_sentientia_evaluation_assign} a
               JOIN {local_sentientia_evaluation} e ON e.id = a.evaluationid
              WHERE a.userid = :uid
           ORDER BY a.timecreated ASC, a.id ASC",
            ['uid' => $userid]);
        foreach ($assignments as $a) {
            $time = (int) ($a->responded_at ?: ($a->due_at ?: $a->timecreated));
            self::merge($rows, (int) $a->evaluationid, (string) $a->name, (int) $a->anonymous, (string) $a->status, $time);
        }

        // Named responses. An anonymous one has user id 0 and is not here. The join only supplies the name and
        // the form's own anonymity flag; the learner is matched on the response itself.
        $responses = $DB->get_records_sql(
            "SELECT r.id, r.evaluationid, r.timesubmitted, e.name, e.anonymous
               FROM {local_sentientia_evaluation_responses} r
               JOIN {local_sentientia_evaluation} e ON e.id = r.evaluationid
              WHERE r.userid = :uid AND r.timesubmitted > 0
           ORDER BY r.timesubmitted ASC, r.id ASC",
            ['uid' => $userid]);
        foreach ($responses as $r) {
            self::merge($rows, (int) $r->evaluationid, (string) $r->name, (int) $r->anonymous, self::STATUS_RESPONDED,
                (int) $r->timesubmitted);
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = (object) [
                'evaluationid' => $row->evaluationid,
                'name' => $row->name,
                'status' => $row->status,
                'time' => $row->time,
                // Sticky, as the admin pages read it: anonymous now, answered anonymously before, or with an
                // anonymous question.
                'anonymous' => evaluation_manager::identity_protected((object) [
                    'id' => $row->evaluationid, 'anonymous' => $row->flag,
                ]),
                'imported' => evaluation_manager::is_imported($row->evaluationid),
            ];
        }
        usort($out, static fn(\stdClass $a, \stdClass $b): int => [$b->time, $b->evaluationid] <=> [$a->time, $a->evaluationid]);
        return $out;
    }

    /**
     * Fold one assignment or response into the learner's row for its evaluation.
     *
     * Responded beats waiting, and waiting beats closed; within the same status the latest time wins. Once the
     * learner has responded, a later waiting or closed assignment for the same form does not undo it.
     *
     * @param \stdClass[] $rows Rows so far, by evaluation id.
     * @param int $evaluationid
     * @param string $name
     * @param int $flag The form's own anonymous flag.
     * @param string $status
     * @param int $time
     * @return void
     */
    private static function merge(array &$rows, int $evaluationid, string $name, int $flag, string $status, int $time): void {
        if (!isset($rows[$evaluationid])) {
            $rows[$evaluationid] = (object) [
                'evaluationid' => $evaluationid, 'name' => $name, 'flag' => $flag, 'status' => $status, 'time' => $time,
            ];
            return;
        }
        $row = $rows[$evaluationid];
        if ($status === self::STATUS_RESPONDED) {
            $row->time = $row->status === self::STATUS_RESPONDED ? max($row->time, $time) : $time;
            $row->status = self::STATUS_RESPONDED;
        } else if ($row->status !== self::STATUS_RESPONDED) {
            if ($status === self::STATUS_ASSIGNED) {
                $row->status = self::STATUS_ASSIGNED;
            }
            $row->time = max($row->time, $time);
        }
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Bulk-mark attendance for many users in one go.
 *
 * Backs "Mark all present", "Save attendance", and any UI that submits the
 * full attendance grid in one shot. Capped at 1000 marks per call.
 *
 * @package   local_sentientia_classroom
 */
class bulk_mark_attendance extends external_api {

    private const MAX_MARKS = 1000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
            'marks'     => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'status' => new external_value(PARAM_INT, '0=absent 1=present 2=late 3=excused'),
                    'notes'  => new external_value(PARAM_TEXT, 'Notes', VALUE_DEFAULT, ''),
                ]),
                'Attendance marks'
            ),
            'loadedat'  => new external_value(PARAM_INT,
                'Unix time the attendance grid was loaded. A learner marked by someone else (a QR '
                . 'scan, another trainer) at or after this time is not turned back to Absent by '
                . 'this save. 0 = no guard.', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $sessionid, array $marks, int $loadedat = 0): array {
        // Taken before anything is read or written: it becomes the grid's new load time
        // (see 'savedat' below), so a scan that lands during this save is still "newer".
        $savedat = time();
        $params = self::validate_parameters(self::execute_parameters(),
            ['sessionid' => $sessionid, 'marks' => $marks, 'loadedat' => $loadedat]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:attendance', $context);

        if (count($params['marks']) > self::MAX_MARKS) {
            throw new \moodle_exception('toomanymarks', 'local_sentientia_classroom');
        }
        // ADR-031: the capability says WHAT; the classroom must also be in the caller's tenant.
        // Attendance is compliance evidence: only learners in it are marked. Without :update the
        // caller must also be the session's or classroom's assigned trainer (owner decision
        // 2026-09-30, session_manager::may_run_session()).
        \local_sentientia_classroom\session_manager::require_attendance_access($params['sessionid']);

        // A mark naming a learner outside the caller's tenant (or with no
        // open_path) is SKIPPED, not a reason to refuse the batch: an
        // in-tenant roster can legitimately hold such a learner (site-admin,
        // approval-flow or pre-fix enrolments), and until 2026-09-25 one of
        // them made the whole grid's Save fail. Cross-tenant callers skip
        // nothing.
        $inscope = array_flip(\local_sentientia_classroom\session_manager::users_in_scope(
            array_column($params['marks'], 'userid')));
        $marks = [];
        $skipped = 0;
        foreach ($params['marks'] as $m) {
            if (isset($inscope[(int) $m['userid']])) {
                $marks[] = $m;
            } else if ((int) $m['userid'] > 0) {
                $skipped++;   // (non-positive ids were never saved; not counted)
            }
        }

        // A learner who scanned the QR code (or was marked by someone else) after the grid was
        // loaded keeps that mark when the trainer's Save sends Absent for them: the trainer
        // never saw the newer mark, so the Save must not wipe it out. $kept counts them.
        $kept = 0;
        $keptusers = [];
        $newerusers = [];
        // $newerusers: every mark somebody else made since the grid was loaded, read in the
        // same transaction as the writes and limited to the caller's roster scope (ADR-031).
        $count = \local_sentientia_classroom\session_manager::bulk_mark_attendance(
            $params['sessionid'], $marks, $params['loadedat'], $kept, $keptusers, $newerusers, true);

        $message = $count . ' ' . ($count === 1 ? 'attendance' : 'attendances') . ' saved.';
        if ($skipped > 0) {
            $message .= ' ' . get_string('attendance_skipped_outoftenant', 'local_sentientia_classroom', $skipped);
        }
        if ($kept > 0) {
            $message .= ' ' . get_string('attendance_kept_newer', 'local_sentientia_classroom', $kept);
        }
        $keptmarks = [];
        foreach ($keptusers as $userid => $status) {
            $keptmarks[] = ['userid' => (int) $userid, 'status' => (int) $status];
        }
        // The grid shows these too. Those already reported as kept are not counted twice in the
        // message (their own sentence above covers them).
        $newermarks = [];
        $unreported = 0;
        foreach ($newerusers as $userid => $status) {
            $newermarks[] = ['userid' => (int) $userid, 'status' => (int) $status];
            if (!isset($keptusers[$userid])) {
                $unreported++;
            }
        }
        if ($unreported > 0) {
            $message .= ' ' . get_string('attendance_newer_shown', 'local_sentientia_classroom', $unreported);
        }
        return [
            'sessionid' => $params['sessionid'],
            'marked'    => $count,
            'skipped'   => $skipped,
            'kept'      => $kept,
            'keptmarks' => $keptmarks,
            'newermarks' => $newermarks,
            'savedat'   => $savedat,
            'message'   => $message,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT,  'Session ID'),
            'marked'    => new external_value(PARAM_INT,  'Rows persisted'),
            'skipped'   => new external_value(PARAM_INT,
                'Marks not saved: the learner is outside the caller\'s tenant (ADR-031)'),
            'kept'      => new external_value(PARAM_INT,
                'Marks not saved because the learner was marked by someone else after the grid was loaded'),
            'keptmarks' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'status' => new external_value(PARAM_INT, 'The mark that stands: 0=absent 1=present 2=late 3=excused'),
                ]),
                'The learners whose newer mark was kept, with the mark that stands',
                VALUE_DEFAULT, []
            ),
            'newermarks' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'status' => new external_value(PARAM_INT, 'The mark that stands: 0=absent 1=present 2=late 3=excused'),
                ]),
                'Every mark somebody else made at or after the grid load time (a QR scan, another '
                . 'trainer), including the kept ones, read in the same transaction as the writes and '
                . 'limited to the caller\'s roster scope. The grid shows them before it moves its '
                . 'load time to savedat',
                VALUE_DEFAULT, []
            ),
            'savedat'   => new external_value(PARAM_INT,
                'Server time this save started. The grid uses it as its new load time, so the next '
                . 'Save only protects marks made after this one (the trainer has seen every earlier '
                . 'mark: the ones this save wrote, and keptmarks and newermarks)',
                VALUE_DEFAULT, 0),
            'message'   => new external_value(PARAM_TEXT, 'Confirmation'),
        ]);
    }
}

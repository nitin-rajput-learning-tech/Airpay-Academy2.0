<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\lrs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\model\statement;

/**
 * cmi5 session tracker.
 *
 * Processes cmi5 Assignable Unit (AU) lifecycle statements and
 * maintains session records in {local_sentientia_xapi_cmi5}.
 *
 * cmi5 mandatory verb set (Quartz release, §9.3):
 *   initialized  → session opened
 *   terminated   → session cleanly ended
 *   suspended    → paused (save-and-exit)
 *   resumed      → re-enter after suspend
 *   abandoned    → forcibly ended (e.g. timeout)
 *   passed       → AU signalled mastery
 *   failed       → AU signalled non-mastery
 *   completed    → AU content consumed (regardless of pass/fail)
 *   satisfied    → LMS-calculated: all AUs in a block are complete
 *   waived       → credit granted without AU launch (exemption)
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmi5_tracker {

    /** cmi5 verb IRIs — subset of the Sentientia xAPI verb library. */
    public const CMI5_VERBS = [
        'initialized' => statement::VERB_INITIALIZED,
        'terminated'  => statement::VERB_TERMINATED,
        'suspended'   => statement::VERB_SUSPENDED,
        'resumed'     => statement::VERB_RESUMED,
        'abandoned'   => statement::VERB_ABANDONED,
        'passed'      => statement::VERB_PASSED,
        'failed'      => statement::VERB_FAILED,
        'completed'   => statement::VERB_COMPLETED,
        'satisfied'   => statement::VERB_SATISFIED,
        'waived'      => statement::VERB_WAIVED,
    ];

    /** Terminal session statuses — no further updates allowed. */
    public const TERMINAL_STATUSES = ['terminated', 'abandoned', 'failed', 'passed'];

    /**
     * Process a stored statement and update the cmi5 session if applicable.
     *
     * Called by the LRS endpoint after a statement is successfully stored.
     * No-ops for non-cmi5 statements (verbs not in CMI5_VERBS).
     *
     * @param array $stmt_data    Raw statement array.
     * @param int   $costcenterid Tenant root.
     * @param int   $userid       Resolved Moodle user id.
     */
    public function process(array $stmt_data, int $costcenterid, int $userid): void {
        global $DB;

        $verb = $stmt_data['verb']['id'] ?? '';
        $verb_key = array_search($verb, self::CMI5_VERBS, true);
        if ($verb_key === false) {
            return;  // Not a cmi5 verb — nothing to track.
        }

        $registration = $stmt_data['context']['registration'] ?? null;
        if (empty($registration)) {
            return;  // cmi5 statements MUST carry a registration UUID.
        }

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $registration]);

        if (!$session) {
            // Create session on initialized (or any cmi5 verb if initialized is missing).
            $session               = new \stdClass();
            $session->userid       = $userid;
            $session->costcenterid = $costcenterid;
            $session->courseid     = 0;  // May be enriched by admin/integration.
            $session->registration = $registration;
            $session->activityid   = $stmt_data['object']['id'] ?? null;
            $session->sessionid    = $stmt_data['context']['extensions']
                ['https://w3id.org/xapi/cmi5/context/extensions/sessionid'] ?? null;
            $session->launchmode   = $stmt_data['context']['extensions']
                ['https://w3id.org/xapi/cmi5/context/extensions/launchmode'] ?? null;
            $session->status       = $verb_key;
            $session->timecreated  = time();
            $session->timemodified = time();

            if ($verb_key === 'initialized') {
                $session->timeinitialized = time();
            }

            $DB->insert_record('local_sentientia_xapi_cmi5', $session);
            return;
        }

        // Do not update terminal sessions.
        if (in_array($session->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $session->status       = $verb_key;
        $session->timemodified = time();

        if ($verb_key === 'terminated') {
            $session->timeterminated = time();
            // Capture final score and duration from result.
            if (!empty($stmt_data['result']['score']['scaled'])) {
                $session->score_scaled = (float) $stmt_data['result']['score']['scaled'];
            }
            if (isset($stmt_data['result']['success'])) {
                $session->success = $stmt_data['result']['success'] ? 1 : 0;
            }
            if (!empty($stmt_data['result']['duration'])) {
                $session->duration = substr((string) $stmt_data['result']['duration'], 0, 32);
            }
        } elseif (in_array($verb_key, ['passed', 'failed'], true)) {
            if (!empty($stmt_data['result']['score']['scaled'])) {
                $session->score_scaled = (float) $stmt_data['result']['score']['scaled'];
            }
            if (isset($stmt_data['result']['success'])) {
                $session->success = $stmt_data['result']['success'] ? 1 : 0;
            }
        }

        $DB->update_record('local_sentientia_xapi_cmi5', $session);
    }

    /**
     * Get all cmi5 sessions for a user, optionally filtered by course.
     *
     * @param int      $userid       Moodle user id.
     * @param int      $costcenterid Tenant root.
     * @param int|null $courseid     Filter by course (null = all courses).
     * @return \stdClass[]
     */
    public function get_sessions(int $userid, int $costcenterid, ?int $courseid = null): array {
        global $DB;

        $conditions = ['userid' => $userid, 'costcenterid' => $costcenterid];
        if ($courseid !== null) {
            $conditions['courseid'] = $courseid;
        }

        return array_values($DB->get_records(
            'local_sentientia_xapi_cmi5',
            $conditions,
            'timemodified DESC'
        ));
    }
}

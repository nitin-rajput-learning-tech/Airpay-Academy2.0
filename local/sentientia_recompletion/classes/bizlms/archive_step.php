<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * A step that turns one row of a legacy archive table into one row of the evidence archive.
 *
 * Every such step writes the same target, keeps the whole source row as the payload (so nothing the legacy
 * plugin archived is lost, whatever a reader shows today), and promotes the few columns a reader needs. The
 * cycle a row belongs to (historyid) is not known until every history row exists, so it is left 0 here and
 * set by the archive_cycles recompute step.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class archive_step extends step {

    /**
     * {@inheritdoc}
     */
    public function targettable(): string {
        return sources::ARCHIVE;
    }

    /**
     * Build an archive row.
     *
     * @param context $ctx
     * @param \stdClass $source The legacy row; the payload is this, unchanged.
     * @param int $userid 0 for an anonymous response.
     * @param int $courseid
     * @param string $type One of mapper::item_types().
     * @param array $fields Optional cmid, instanceid, parentid, itemkey, state, grade (float|null), timeevent.
     * @return \stdClass
     */
    protected static function archive_row(context $ctx, \stdClass $source, int $userid, int $courseid, string $type,
                                          array $fields = []): \stdClass {
        $timeevent = $fields['timeevent'] ?? null;
        $itemkey = $fields['itemkey'] ?? null;
        $state = $fields['state'] ?? null;

        // The reset that ended the cycle, when its history row already exists (every step after the completion
        // step can see it; the completion step itself writes its inferred history rows in the same batch, so its
        // rows are attached by the archive_cycles recompute step, which also repairs any row this gets wrong).
        $historyid = 0;
        $archivedat = $timeevent ?? 0;
        if ($timeevent !== null && $type !== mapper::COURSE_COMPLETION) {
            $reset = evidence::of($ctx)->cycle($userid, $courseid, $timeevent);
            if ($reset !== null) {
                $historyid = $reset['id'];
                $archivedat = $reset['time'];
            }
        }

        return (object) [
            'historyid' => $historyid,
            'userid' => $userid,
            'courseid' => $courseid,
            'itemtype' => $type,
            'cmid' => $fields['cmid'] ?? null,
            'instanceid' => $fields['instanceid'] ?? null,
            'parentid' => $fields['parentid'] ?? null,
            'itemkey' => $itemkey === null ? null : $ctx->text->fit((string) $itemkey, 255, 'itemkey'),
            'state' => $state === null ? null : $ctx->text->fit((string) $state, 30, 'state'),
            'grade' => $fields['grade'] ?? null,
            'timeevent' => $timeevent,
            'payload' => mapper::payload($source),
            // "Archived at": the reset time of the cycle when it is known, else the time of the event itself.
            'timecreated' => $archivedat,
        ];
    }

    /**
     * The outcome for a row whose learner has no user row: the evidence of a person who is not there is not
     * invented a home, and the row stays in the legacy table.
     *
     * @param int $id Legacy row id.
     * @return outcome
     */
    protected static function orphan_user(int $id): outcome {
        return outcome::skip($id, 'orphan_user', 'user_not_found');
    }

    /**
     * Is this a learner the restored database has a user row for (a deleted user still has one)?
     *
     * @param context $ctx
     * @param int $userid
     * @return bool
     */
    protected static function known_user(context $ctx, int $userid): bool {
        return $userid > 0 && $ctx->lookups->user_exists($userid);
    }
}

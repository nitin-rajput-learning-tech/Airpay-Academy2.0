<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\output;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_live\session_manager;
use local_sentientia_live\slide_manager;
use local_sentientia_live\participant_manager;

/**
 * Trainer dashboard renderable — Phase E.1.f.
 *
 * Renders a list of the trainer's own sessions with:
 *   - state badge (draft / live / ended)
 *   - 6-digit join code (highlighted for live sessions)
 *   - slide count
 *   - audience count (active for live sessions; total for ended ones)
 *   - timestamps + action buttons (edit / run / end / delete)
 *
 * @package local_sentientia_live
 */
class trainer_dashboard implements \renderable, \templatable {

    public function __construct(
        private int $ownerid,
        private string $createurl,
    ) {}

    /**
     * @param \renderer_base $output
     * @return array Mustache context.
     */
    public function export_for_template(\renderer_base $output): array {
        $sessions = session_manager::list_owned_by($this->ownerid, null, 200);

        $rows = [];
        foreach ($sessions as $sess) {
            $is_live = $sess->state === session_manager::STATE_LIVE;
            $is_draft = $sess->state === session_manager::STATE_DRAFT;
            $is_ended = $sess->state === session_manager::STATE_ENDED;

            $slide_count = slide_manager::count_for_session((int) $sess->id);
            $audience_count = $is_live
                ? participant_manager::active_count_for_session((int) $sess->id)
                : participant_manager::total_count_for_session((int) $sess->id);

            // Pretty-print the code: "123456" -> "123 456" (matches what
            // audience sees on the join page).
            $code_pretty = substr($sess->code, 0, 3) . ' ' . substr($sess->code, 3);

            $rows[] = [
                'id'            => (int) $sess->id,
                'title'         => format_string($sess->title),
                'state'         => $sess->state,
                'state_label'   => get_string('state_' . $sess->state, 'local_sentientia_live'),
                'is_draft'      => $is_draft,
                'is_live'       => $is_live,
                'is_ended'      => $is_ended,
                'code'          => $sess->code,
                'code_pretty'   => $code_pretty,
                'slide_count'   => $slide_count,
                'audience_count'=> $audience_count,
                'timecreated_formatted' => userdate((int) $sess->timecreated,
                    get_string('strftimedatetimeshort', 'langconfig')),
                'edit_url'      => (new \moodle_url(
                    '/local/sentientia_live/trainer/edit.php',
                    ['id' => (int) $sess->id]))->out(false),
                'run_url'       => (new \moodle_url(
                    '/local/sentientia_live/trainer/run.php',
                    ['id' => (int) $sess->id]))->out(false),
                'end_url'       => (new \moodle_url(
                    '/local/sentientia_live/trainer/end.php',
                    ['id' => (int) $sess->id, 'sesskey' => sesskey()]))->out(false),
                'delete_url'    => (new \moodle_url(
                    '/local/sentientia_live/trainer/delete.php',
                    ['id' => (int) $sess->id, 'sesskey' => sesskey()]))->out(false),
            ];
        }

        return [
            'sessions'     => $rows,
            'has_sessions' => !empty($rows),
            'create_url'   => $this->createurl,
            'session_count'=> count($rows),
        ];
    }
}

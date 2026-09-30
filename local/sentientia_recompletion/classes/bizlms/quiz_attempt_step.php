<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived quiz attempt (quiz_attempts).
 *
 * The payload is the whole row, including uniqueid: the legacy plugin deleted the attempt rows directly, so the
 * question usage that hangs off uniqueid is gone with them, and this row is what is left of the attempt. The
 * grade is the raw marks (sumgrades); a reader scales it by the quiz's grade and sumgrades. A teacher's preview
 * attempt is not learner history and stays in the legacy table (owner decision recompletion.preview_attempts).
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_attempt_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.qa';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::QA;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx);
        $previews = $ctx->decision('recompletion.preview_attempts');
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ((int) ($row->preview ?? 0) === 1 && $previews === 'skip') {
                $out[] = outcome::archive($id, 'preview_attempt');
                continue;
            }
            $user = (int) $row->userid;
            if (!self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }
            $quiz = (int) $row->quiz;
            $course = (int) $row->course > 0 ? (int) $row->course : $evidence->quiz_course($quiz);
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::QUIZ_ATTEMPT, [
                    'cmid' => $evidence->quiz_cmid($quiz),
                    'instanceid' => $quiz,
                    'state' => (string) $row->state,
                    'grade' => mapper::bounded_number($row->sumgrades),
                    'timeevent' => mapper::first_timestamp([$row->timefinish, $row->timemodified, $row->timestart]),
                ]));
        }
        return $out;
    }
}

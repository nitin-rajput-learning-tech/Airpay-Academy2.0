<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * The two conditional BizLMS trainer tables: local_program_trainers -> local_sentientia_programs_trainers and
 * local_program_trainerfb -> local_sentientia_programs_trainerfb (both MAP).
 *
 * BizLMS has no writer for either (it only reads and deletes them: program.php:1305, externallib.php:135-136),
 * so both are expected to be empty. The steps exist so that a row that does turn up is carried, not dropped.
 * Nothing in Sentientia reads the target tables yet.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trainer_step extends base_step {

    /** @var bool True for the feedback table, false for the trainer assignments. */
    private bool $feedback;

    /**
     * @param bool $feedback
     */
    public function __construct(bool $feedback) {
        $this->feedback = $feedback;
    }

    public function key(): string {
        return $this->feedback ? 'program.trainerfeedback' : 'program.trainer';
    }

    public function sourcetable(): string {
        return $this->feedback ? 'local_program_trainerfb' : 'local_program_trainers';
    }

    public function targettable(): string {
        return $this->feedback ? self::T_TRAINERFB : self::T_TRAINERS;
    }

    public function columns(): array {
        return $this->feedback
            ? ['bc_trainer_id', 'programid', 'trainerid', 'userid', 'score', 'timecreated', 'timemodified']
            : ['programid', 'trainerid', 'feedback_id', 'feedback_score', 'usercreated', 'timecreated', 'timemodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->feedback ? $this->feedback_row($row, $ctx) : $this->trainer_row($row, $ctx);
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function trainer_row(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        $program = $ctx->map->resolve('local_program', (int) $row->programid);
        if ($program === null) {
            return outcome::skip($id, 'orphan_program');
        }
        if (!$ctx->lookups->user_exists((int) $row->trainerid)) {
            return outcome::skip($id, 'orphan_user');
        }
        $created = (int) $row->timecreated;
        return outcome::insert($id, self::T_TRAINERS, (object) [
            'programid' => $program,
            'userid' => (int) $row->trainerid,
            'feedbackid' => max(0, (int) $row->feedback_id),
            'feedback_score' => $ctx->text->fit((string) $row->feedback_score, 45, 'feedback_score'),
            'assignedby' => max(0, (int) $row->usercreated),
            'timecreated' => $created,
            'timemodified' => rules::time_or($row->timemodified, $created),
        ]);
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function feedback_row(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        $program = $ctx->map->resolve('local_program', (int) $row->programid);
        if ($program === null) {
            return outcome::skip($id, 'orphan_program');
        }
        $trainerrow = $ctx->map->resolve('local_program_trainers', (int) $row->bc_trainer_id);
        if ($trainerrow === null) {
            return outcome::skip($id, 'orphan_trainer');
        }
        if (!$ctx->lookups->user_exists((int) $row->trainerid)) {
            return outcome::skip($id, 'orphan_user');
        }
        // The learner who gave the feedback is optional in BizLMS; one that no longer exists is left empty.
        $giver = $row->userid === null ? 0 : (int) $row->userid;
        $giverexists = $giver > 0 && $ctx->lookups->user_exists($giver);
        $created = (int) $row->timecreated;
        $result = outcome::insert($id, self::T_TRAINERFB, (object) [
            'programtrainerid' => $trainerrow,
            'programid' => $program,
            'trainerid' => (int) $row->trainerid,
            'userid' => $giverexists ? $giver : null,
            'score' => $ctx->text->fit((string) $row->score, 45, 'score'),
            'timecreated' => $created,
            'timemodified' => rules::time_or($row->timemodified, $created),
        ]);
        if ($giver > 0 && !$giverexists) {
            $result->warn('feedback_giver_not_found');
        }
        return $result;
    }
}

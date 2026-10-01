<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_transcript_history -> local_sentientia_users_transcript (ADR-032, mapping doc section 10): the
 * 2015-2016 spreadsheet load of earlier training records.
 *
 * No BizLMS code ever wrote or read this table, so nothing depends on its ids (MAP) and every row is history:
 * all rows are imported, none is skipped, and none counts toward completion totals (signed decision
 * users.transcript_counts_toward_totals = false). It never touches course_completions, the standard log or the
 * xAPI store.
 *
 * Per row:
 *  - the learner: the userid when it names an account, else the one live account the employee id names
 *    (user_identity_index), else 0 (an off-platform record);
 *  - the raw text of the date, status, score and hours is kept, and the parsed value stored next to it where the
 *    text parses (transcript_parser); the status is normalised by the owner's signed list;
 *  - the course: kept when it exists and is not the front page, else 0;
 *  - the tenant: the matched learner's CURRENT org path, normalised and checked by the framework's resolver; a
 *    row with no learner, or whose learner has no resolvable path, imports with no path and is visible to
 *    cross-tenant callers only (signed decision tenant.unresolved.users = pathless);
 *  - times: the source's, NULL as 0.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transcript_step extends step {

    /** The only table the step writes. */
    public const TARGET = 'local_sentientia_users_transcript';

    public function key(): string {
        return 'users.transcript';
    }

    public function sourcetable(): string {
        return 'local_transcript_history';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function transform(array $rows, context $ctx): array {
        $identity = user_identity_index::for_context($ctx);
        $statusmap = (array) $ctx->decision(users_importer::DECISION_STATUS_MAP);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $warnings = [];

            $userid = clean::id($row->userid ?? null);
            if ($userid === 0 || !$ctx->lookups->user_exists($userid)) {
                $userid = $identity->resolve($row->employee_id ?? null);
                if ($userid > 0) {
                    $warnings[] = 'user_matched_by_employee_id';
                } else {
                    $warnings[] = 'no_user';
                }
            }
            [$path, $root, $method] = $ctx->tenant->resolve(
                ['user' => $userid > 0 ? $ctx->lookups->user_path($userid) : null]);

            $courseid = clean::id($row->courseid ?? null);
            if ($courseid === 0 || $courseid === SITEID || !$ctx->lookups->course_exists($courseid)) {
                $courseid = 0;
            }

            $statusraw = $this->text($ctx, $row->status ?? null, 'status_raw', $warnings);
            $dateraw = $this->text($ctx, $row->completion_date ?? null, 'completion_date_raw', $warnings);
            $scoreraw = $this->text($ctx, $row->transcript_score ?? null, 'score_raw', $warnings);
            $hoursraw = $this->text($ctx, $row->training_hours ?? null, 'hours_raw', $warnings);
            $title = $this->text($ctx, $row->training_title ?? null, 'title', $warnings);

            $completed = transcript_parser::date($dateraw, $ctx->servertz());
            if ($completed === null && trim($dateraw) !== '') {
                $warnings[] = 'unparsed_date';
            }
            $score = transcript_parser::score($scoreraw);
            if ($score === null && trim($scoreraw) !== '') {
                $warnings[] = 'unparsed_score';
            }
            $hours = transcript_parser::hours($hoursraw);
            if ($hours === null && trim($hoursraw) !== '') {
                $warnings[] = 'unparsed_hours';
            }

            $outcome = outcome::insert($id, self::TARGET, (object) [
                'userid' => $userid,
                'employee_id' => $this->text($ctx, $row->employee_id ?? null, 'employee_id', $warnings),
                'learner_name' => $this->text($ctx, $row->fullname ?? null, 'learner_name', $warnings),
                'title' => trim($title) !== '' ? $title : '(untitled)',
                'training_type' => $this->text($ctx, $row->training_type ?? null, 'training_type', $warnings),
                'objectref' => $this->text($ctx, $row->training_object_id ?? null, 'objectref', $warnings),
                'location' => $this->text($ctx, $row->training_location ?? null, 'location', $warnings),
                'courseid' => $courseid,
                'status' => transcript_parser::status($statusraw, $statusmap),
                'status_raw' => $statusraw,
                'completion_date_raw' => $dateraw,
                'score_raw' => $scoreraw,
                'hours_raw' => $hoursraw,
                'timecompleted' => $completed,
                'score' => $score,
                'hours' => $hours,
                'costcenterid' => $root === null ? 0 : (int) $root,
                'open_path' => $path,
                'source' => 'bizlms',
                'usercreated' => clean::id($row->usercreated ?? null),
                'usermodified' => clean::id($row->usermodified ?? null),
                'timecreated' => clean::time($row->timecreated ?? null),
                'timemodified' => clean::time($row->timemodified ?? null),
            ])->tenant_method($method);
            foreach ($warnings as $code) {
                $outcome->warn($code);
            }
            $out[] = $outcome;
        }
        return $out;
    }

    /**
     * A CHAR(255) value: valid UTF-8, trimmed of nothing (the text is kept as loaded), fitted to the column with a
     * truncated:<column> warning.
     *
     * @param context $ctx
     * @param mixed $value
     * @param string $column Target column, for the warning code.
     * @param string[] $warnings Collects invalid_utf8.
     * @return string
     */
    private function text(context $ctx, mixed $value, string $column, array &$warnings): string {
        [$text, $repaired] = clean::utf8($value === null ? null : (string) $value);
        if ($repaired && !in_array('invalid_utf8', $warnings, true)) {
            $warnings[] = 'invalid_utf8';
        }
        return $ctx->text->fit($text, 255, $column);
    }
}

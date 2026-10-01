<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived course completion criterion (course_completion_crit_compl): which criterion the learner met,
 * with the final grade and the time.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.cc_cc';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::CC_CC;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $user = (int) $row->userid;
            if (!self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }
            $completed = mapper::timestamp($row->timecompleted);
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, (int) $row->course,
                mapper::CRITERIA_COMPLETION, [
                    'instanceid' => (int) $row->criteriaid,
                    'state' => $completed !== null ? 'complete' : 'incomplete',
                    'grade' => mapper::bounded_number($row->gradefinal),
                    'timeevent' => $completed,
                ]));
        }
        return $out;
    }
}

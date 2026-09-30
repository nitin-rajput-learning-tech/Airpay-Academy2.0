<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived questionnaire response (questionnaire_response): the header of one submission.
 *
 * The answers are in seven child tables that point at the response by its ORIGINAL id (originalresponseid),
 * which the payload keeps. A response with no learner (an anonymous one) is imported with user 0 and reported;
 * a response whose learner has no user row is skipped like any other such row.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.qr';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::QR;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $user = $row->userid === null ? 0 : (int) $row->userid;
            $anonymous = $user === 0;
            if (!$anonymous && !self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }
            $outcome = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, (int) $row->course,
                mapper::QUESTIONNAIRE_RESPONSE, [
                    'instanceid' => (int) $row->questionnaireid,
                    'state' => $row->complete === 'y' ? 'complete' : 'incomplete',
                    'grade' => mapper::bounded_number($row->grade),
                    'timeevent' => mapper::timestamp($row->submitted),
                ]));
            if ($anonymous) {
                $outcome->warn('anonymous_response');
            }
            $out[] = $outcome;
        }
        return $out;
    }
}

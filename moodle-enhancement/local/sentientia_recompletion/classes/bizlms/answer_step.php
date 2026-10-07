<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived questionnaire answer: one row of one of the seven answer tables (yes/no, date, multiple choice,
 * other, rank, single choice, text), as a child of its response.
 *
 * The child rows keep the ORIGINAL response id, not the id of the archived response row, so the parent is found
 * through that (evidence::responses()) and then through the map. The learner, course and time are the
 * parent's. An answer whose response is not in the archive, or was not imported, is skipped and reported: an
 * answer with no response says nothing. The free text of an answer (text, other, date) is personal data and is
 * in the payload, which the privacy provider scrubs.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class answer_step extends archive_step {

    /** @var string[] kind => legacy table. */
    public const TABLES = [
        'bool' => sources::QR_BOOL, 'date' => sources::QR_DATE, 'm' => sources::QR_M, 'other' => sources::QR_OTHER,
        'rank' => sources::QR_RANK, 'single' => sources::QR_SINGLE, 'text' => sources::QR_TEXT,
    ];

    /** @var string One of the keys of TABLES. */
    private string $kind;

    /**
     * @param string $kind One of the keys of TABLES.
     */
    public function __construct(string $kind) {
        if (!isset(self::TABLES[$kind])) {
            throw new \coding_exception('unknown questionnaire answer kind');
        }
        $this->kind = $kind;
    }

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.qr_' . $this->kind;
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return self::TABLES[$this->kind];
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $responses = evidence::of($ctx)->responses();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $parent = $responses[(int) $row->response_id] ?? null;
            $parentid = $parent === null ? null : $ctx->map->resolve(sources::QR, $parent['id']);
            if ($parent === null || $parentid === null) {
                $out[] = outcome::skip($id, 'orphan_response', 'response_not_imported');
                continue;
            }
            $fields = [
                'instanceid' => $parent['questionnaireid'],
                'parentid' => $parentid,
                'itemkey' => (string) $row->question_id,
                'timeevent' => mapper::timestamp($parent['submitted']),
            ];
            switch ($this->kind) {
                case 'bool':
                case 'm':
                case 'other':
                case 'single':
                    $fields['state'] = (string) $row->choice_id;
                    break;
                case 'rank':
                    $fields['state'] = (string) $row->choice_id;
                    $fields['grade'] = mapper::bounded_number($row->rankvalue);
                    break;
                default:
                    // date and text: the answer is the payload.
                    break;
            }
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $parent['userid'],
                $parent['course'], mapper::QUESTIONNAIRE_ANSWER, $fields));
        }
        return $out;
    }
}

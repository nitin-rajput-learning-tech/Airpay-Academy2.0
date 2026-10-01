<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluation_template -> local_sentientia_evaluation_template (ADR-032, mapping doc section 18).
 *
 * A BizLMS template is a header row plus items that point at it (local_evaluation_item with evaluation = 0 and
 * template = the template id). A Sentientia template is one row whose payload is the JSON that
 * evaluation_manager::export_template() writes and import_template() reads, so the items are built into the
 * payload here and the question step then FOLDS each of those items into this row (it records the fold; this
 * step decides what the payload holds, through the same answer_mapper, so the two agree).
 *
 * Dependencies between template items are dropped: the payload shape has no field for them.
 *
 * The tenant is the organisation of the template's open_path, else of the root BizLMS kept in costcenterid; none
 * gives costcenterid 0. Templates have no source timestamps, so timecreated and timemodified are the import time
 * (the one place the mapping doc allows time()).
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.templates';

    /** Version of the payload shape (evaluation_manager::TEMPLATE_FORMAT_VERSION). */
    public const PAYLOAD_FORMAT = 1;

    public function key(): string {
        return self::KEY;
    }

    public function sourcetable(): string {
        return importer::SRC_TEMPLATES;
    }

    public function targettable(): string {
        return importer::T_TEMPLATES;
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map_row($row, $ctx);
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function map_row(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        $rawpath = trim((string) ($row->open_path ?? ''));
        [, $costcenterid, $method] = tenant_scope::resolve($ctx, [
            'open_path' => $rawpath === '' ? null : $rawpath,
            'costcenterid' => tenant_scope::root_path($row->costcenterid ?? null),
        ]);

        $name = $ctx->text->fit(trim(answer_mapper::utf8((string) $row->name)), 254, 'name');
        $now = time();
        $payload = [
            'format' => self::PAYLOAD_FORMAT,
            'exported_at' => $now,
            'evaluation' => [
                'name' => $name,
                'description' => '',
                'kirkpatrick_level' => 1,
                'trigger_event' => 'manual',
                'days_after' => 0,
                'anonymous' => 0,
            ],
            'questions' => $this->questions($ctx, $id),
        ];

        return outcome::insert($id, importer::T_TEMPLATES, (object) [
            'name' => $name,
            'description' => '',
            'payload' => json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),
            'createdby_userid' => 0,
            'costcenterid' => $costcenterid,
            'ispublic' => (int) ($row->ispublic ?? 0) === 1 ? 1 : 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ])->tenant_method($method);
    }

    /**
     * The payload's questions: the template's items that are questions, in form order.
     *
     * @param context $ctx
     * @param int $templateid
     * @return array<int, array> Shape of evaluation_manager::export_template() questions.
     */
    private function questions(context $ctx, int $templateid): array {
        $items = $ctx->legacy->page(importer::SRC_ITEMS, 0, legacy_reader::MAX_PAGE,
            ['id', 'name', 'typ', 'presentation', 'position', 'required'],
            ['t.template = :evtt AND t.evaluation = 0', ['evtt' => $templateid]]);
        $questions = [];
        foreach ($items as $item) {
            $shape = answer_mapper::describe($item);
            if ($shape === null) {
                continue;
            }
            $questions[] = [
                'position' => (int) $item->position,
                'id' => (int) $item->id,
                'question' => [
                    'questiontype' => $shape->type,
                    'questiontext' => answer_mapper::normalise_text((string) $item->name),
                    'options' => $shape->options_array(),
                    'required' => (int) $item->required === 1 ? 1 : 0,
                    'anonymous' => 0,
                ],
            ];
        }
        usort($questions, static fn(array $a, array $b): int => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);
        $out = [];
        foreach ($questions as $number => $entry) {
            $out[] = $entry['question'] + ['sortorder' => $number + 1];
        }
        return $out;
    }
}

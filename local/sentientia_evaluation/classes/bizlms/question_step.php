<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluation_item -> local_sentientia_evaluation_questions (ADR-032, mapping doc section 18). MAP.
 *
 * One table, two kinds of row, told apart by which parent they name:
 *
 *  - evaluation > 0: an item of a form. A question becomes a questions row; info, label, captcha and pagebreak
 *    items only lay the form out and stay in the legacy table (archived, not_a_question).
 *  - evaluation = 0 and template > 0: an item of a template. The template step already built it into the
 *    template's payload, so a question is FOLDED into that template row, not written again.
 *
 * Question types: multichoice radio and dropdown -> multichoice, checkboxes -> multichoice_multi, multichoicerated
 * -> multichoice with the weights dropped (decision evaluation.multichoicerated), numeric -> numeric with integer
 * bounds, textfield and textarea -> text. answer_mapper decides; it is the same code that maps the answers.
 *
 * Conditional display. dependvalue is normalised exactly like an option text (BizLMS compared it with the raw
 * option text; Sentientia compares exact trimmed strings), and an EMPTY dependvalue becomes a value no answer can
 * equal, because in BizLMS it matched nothing while in Sentientia NULL means "show on any answer". The parent
 * question (depends_on_qid) is set by the recompute step after this one, because the parent may have a higher id
 * than the child and so may not exist yet.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.questions';

    /** @var form_facts */
    private form_facts $facts;

    /**
     * @param form_facts $facts
     */
    public function __construct(form_facts $facts) {
        $this->facts = $facts;
    }

    public function key(): string {
        return self::KEY;
    }

    public function sourcetable(): string {
        return importer::SRC_ITEMS;
    }

    public function targettable(): string {
        return importer::T_QUESTIONS;
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_FORMS, ''], [importer::SRC_TEMPLATES, '']];
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
        $formid = (int) ($row->evaluation ?? 0);
        $templateid = (int) ($row->template ?? 0);
        $typ = trim((string) ($row->typ ?? ''));

        if ($formid > 0) {
            [$target, $problem] = $this->facts->form_target($ctx, $formid);
            if ($target === null) {
                return $problem === 'orphan_form' ? outcome::skip($id, 'orphan_form', 'form_not_found')
                    : outcome::archive($id, 'parent_deleted');
            }
        } else if ($templateid > 0) {
            $entry = $ctx->map->entry(importer::SRC_TEMPLATES, $templateid);
            if ($entry === null || $entry['targetid'] === null) {
                return outcome::skip($id, 'orphan_template', 'template_not_found');
            }
            $target = (int) $entry['targetid'];
        } else {
            return outcome::skip($id, 'orphan_item', 'no_parent');
        }

        if (!answer_mapper::is_known_type($typ)) {
            return outcome::skip($id, 'unmapped_enum', 'item_type_unknown');
        }
        $shape = answer_mapper::describe($row);
        if ($shape === null) {
            return outcome::archive($id, 'not_a_question');
        }
        if ($formid <= 0) {
            return outcome::fold($id, importer::T_TEMPLATES, $target, 'in_template_payload');
        }

        $text = answer_mapper::normalise_text((string) $row->name);
        $dependvalue = null;
        if ((int) ($row->dependitem ?? 0) > 0) {
            $normalised = answer_mapper::normalise_text((string) ($row->dependvalue ?? ''));
            $dependvalue = $normalised === '' ? answer_mapper::NEVER_MATCHES
                : $ctx->text->fit($normalised, 255, 'depends_on_value');
        }
        $form = $this->facts->form($ctx, $formid);
        $created = $form === null ? 0 : $this->facts->created($ctx, $form);

        $outcome = outcome::insert($id, importer::T_QUESTIONS, (object) [
            'evaluationid' => $target,
            'questiontype' => $shape->type,
            'questiontext' => $text,
            'options' => $shape->options_json(),
            'required' => (int) ($row->required ?? 0) === 1 ? 1 : 0,
            'anonymous' => 0,
            // Set by the recompute step, once every question of the form exists.
            'depends_on_qid' => null,
            'depends_on_value' => $dependvalue,
            'sortorder' => max(0, (int) ($row->position ?? 0)),
            'timecreated' => $created,
        ]);
        if ($text === '') {
            $outcome->warn('empty_question_text');
        }
        if ($shape->type === 'numeric' && $shape->bounds_truncated()) {
            $outcome->warn('truncated:numeric_bound');
        }
        if (($shape->type === 'multichoice' || $shape->type === 'multichoice_multi') && !$shape->options()) {
            $outcome->warn('no_options');
        }
        if ($dependvalue === answer_mapper::NEVER_MATCHES) {
            $outcome->warn('dependvalue_empty');
        }
        return $outcome;
    }
}

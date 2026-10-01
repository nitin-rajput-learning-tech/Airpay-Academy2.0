<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Second pass over the imported questions: the parent a conditional question depends on (ADR-032, mapping doc
 * section 18, "dependitem -> depends_on_qid").
 *
 * The question step cannot set depends_on_qid, because a BizLMS item may depend on an item with a HIGHER id (the
 * form's items can be reordered), which has not been written yet. Here every question of the form exists.
 *
 * A parent that was not imported, or that belongs to another form, leaves the question unconditional: both
 * depends_on_qid and depends_on_value become NULL, because a value with no parent means nothing and the row
 * would otherwise look conditional. This is what BizLMS showed a learner too (a dependency on a missing item is
 * ignored).
 *
 * Idempotent, as a recompute step must be: it sets the same values whenever it runs.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dependency_step extends recompute_step {

    /** Step key. */
    public const KEY = 'evaluation.question_dependencies';

    /** Rows per page when the dependent items are listed. */
    private const PAGE = 5000;

    /** @var array<int, array{form: int, parent: int}>|null Dependent item id => its form and its parent item. */
    private ?array $dependents = null;

    /** @var array<int, int> Parent item id => the form it belongs to. */
    private array $parentforms = [];

    public function key(): string {
        return self::KEY;
    }

    public function targettable(): string {
        return importer::T_QUESTIONS;
    }

    /**
     * @param int[] $targetids Sentientia question ids this batch covers.
     * @param context $ctx
     * @return outcome[]
     */
    public function recompute(array $targetids, context $ctx): array {
        $dependents = $this->dependents($ctx);
        if (!$dependents) {
            return [];
        }
        $wanted = array_flip($targetids);
        $own = $ctx->map->resolve_many(importer::SRC_ITEMS, array_keys($dependents));
        $parents = $ctx->map->resolve_many(importer::SRC_ITEMS, array_values(array_unique(array_column($dependents, 'parent'))));

        $out = [];
        foreach ($dependents as $itemid => $dependent) {
            $question = $own[$itemid] ?? null;
            if ($question === null || !isset($wanted[$question])) {
                continue;
            }
            $parent = $parents[$dependent['parent']] ?? null;
            $sameform = ($this->parentforms[$dependent['parent']] ?? 0) === $dependent['form'];
            $fields = new \stdClass();
            if ($parent !== null && $sameform) {
                $fields->depends_on_qid = $parent;
            } else {
                $fields->depends_on_qid = null;
                $fields->depends_on_value = null;
            }
            $out[] = outcome::update(importer::T_QUESTIONS, (int) $question, $fields);
        }
        return $out;
    }

    /**
     * Every item of a form that names a parent, and the form of each parent, read once.
     *
     * @param context $ctx
     * @return array<int, array{form: int, parent: int}>
     */
    private function dependents(context $ctx): array {
        if ($this->dependents !== null) {
            return $this->dependents;
        }
        $this->dependents = [];
        $after = 0;
        do {
            $rows = $ctx->legacy->page(importer::SRC_ITEMS, $after, self::PAGE, ['id', 'evaluation', 'dependitem'],
                ['t.evaluation > 0 AND t.dependitem > 0', []]);
            foreach ($rows as $id => $row) {
                $after = (int) $id;
                $this->dependents[$after] = ['form' => (int) $row->evaluation, 'parent' => (int) $row->dependitem];
            }
        } while (count($rows) === self::PAGE);

        $parentids = array_values(array_unique(array_column($this->dependents, 'parent')));
        foreach (array_chunk($parentids, legacy_reader::MAX_PAGE) as $chunk) {
            foreach ($ctx->legacy->fetch(importer::SRC_ITEMS, $chunk, ['id', 'evaluation']) as $id => $row) {
                $this->parentforms[(int) $id] = (int) $row->evaluation;
            }
        }
        return $this->dependents;
    }
}

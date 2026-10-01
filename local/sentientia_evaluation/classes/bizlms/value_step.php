<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluation_value: accounting for every stored answer (ADR-032, mapping doc section 18).
 *
 * The answers themselves were written by response_step, into response_data, because in Sentientia a response is
 * ONE row holding all its answers. This step writes nothing. It gives each of the source's value rows the one map
 * entry the framework requires, by asking form_facts which values that response_step accepted (the same code
 * decided it, so they cannot disagree):
 *
 *  - accepted -> FOLDED into its response (in_response_data);
 *  - not accepted -> archived with the reason: the item is not a question here (item_not_imported), a second
 *    value for the same item (duplicate_value), or a value that cannot be an answer to its item
 *    (value_not_valid: a position that does not exist, text where a number belongs). It stays in the legacy
 *    table and the report counts it;
 *  - its completion was not imported -> archived (response_not_imported); no completion row at all -> skipped
 *    (orphan_completed).
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class value_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.values';

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
        return importer::SRC_VALUES;
    }

    public function targettable(): string {
        return importer::T_RESPONSES;
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_FORMS, ''], [importer::SRC_ITEMS, '']];
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
        $completedid = (int) $row->completed;

        $completed = $this->facts->completed($ctx, $completedid);
        if ($completed === null) {
            return outcome::skip($id, 'orphan_completed', 'completion_not_found');
        }
        $entry = $ctx->map->entry(importer::SRC_COMPLETED, $completedid);
        if ($entry === null || !in_array($entry['outcome'], ['imported', 'adopted'], true) || $entry['targetid'] === null) {
            return outcome::archive($id, 'response_not_imported');
        }
        $form = $this->facts->form($ctx, (int) $completed->evaluation);
        if ($form === null) {
            // Cannot happen for an imported response, which needed its form; stay safe.
            return outcome::archive($id, 'response_not_imported');
        }

        $set = $this->facts->answers($ctx, $completed, $form);
        if (isset($set->accepted[$id])) {
            return outcome::fold($id, importer::T_RESPONSES, (int) $entry['targetid'], 'in_response_data');
        }
        return outcome::archive($id, $set->rejected[$id] ?? 'value_not_valid');
    }
}

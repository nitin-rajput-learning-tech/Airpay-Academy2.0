<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * A claimed legacy table whose rows deliberately stay only in the legacy table (ADR-032, outcome "archived").
 *
 * Every row gets its one primary map row, so the accounting identity (source rows = map rows) and the
 * parity check cover the table, and the report says why it was not copied. Nothing is written to a target
 * table: the target named here is only the declared home the framework wants every step to have.
 *
 * Used for local_classroom_trainerfb (the trainer feedback marker, whose answers the evaluation feature
 * imports from the evaluation tables) and local_classroom_completion (per-classroom completion rule
 * configuration, whose outcome is the roster's completion_status).
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_step extends step {

    /** @var string */
    private string $key;

    /** @var string */
    private string $source;

    /** @var string */
    private string $reason;

    /**
     * @param string $key Step key, prefixed by the feature.
     * @param string $source Legacy table.
     * @param string $reason Reason code of the importer's vocabulary.
     */
    public function __construct(string $key, string $source, string $reason) {
        $this->key = $key;
        $this->source = $source;
        $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function key(): string {
        return $this->key;
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return $this->source;
    }

    /**
     * The declared home of the step. Nothing is written to it.
     *
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_trainers';
    }

    /**
     * Only the id is needed.
     *
     * @return string[]
     */
    public function columns(): array {
        return ['id'];
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = outcome::archive((int) $row->id, $this->reason);
        }
        return $out;
    }
}

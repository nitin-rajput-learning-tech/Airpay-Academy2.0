<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Second pass over target rows this run imported, for example a program's
 * currentlevelid or a credit balance (ADR-032, "Importer interface").
 *
 * It may only return update outcomes for rows the import created or adopted.
 *
 * It must be IDEMPOTENT. The runner hands it every such row of the feature,
 * not only the rows this run imported: a fresh apply after a failed run finds
 * all the load steps already done, and scoping the second pass by run would
 * skip it while the feature was still marked complete. (Only --retry-skipped
 * narrows it, to the rows that run moved to a new outcome.)
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class recompute_step {

    /**
     * Step key, unique across the registry.
     *
     * @return string
     */
    abstract public function key(): string;

    /**
     * The target table whose imported rows are recomputed.
     *
     * @return string
     */
    abstract public function targettable(): string;

    /**
     * @param int[] $targetids Ids of target rows the import created or adopted in this run.
     * @param context $ctx
     * @return outcome[] Update outcomes only.
     */
    abstract public function recompute(array $targetids, context $ctx): array;
}

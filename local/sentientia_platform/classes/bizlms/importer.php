<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * One import unit (ADR-032, "Importer interface"). Lives in the TARGET plugin,
 * under classes/bizlms/, and is declared in that plugin's db/bizlms_import.php.
 *
 * An importer never writes another feature's tables, never writes a legacy
 * table, and never deletes any row. It returns outcomes; the writer writes.
 *
 * The contract is frozen after Phase 0: changing it needs an amendment to
 * ADR-032. Optional, additive capabilities are separate interfaces
 * (see watches_tables).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface importer {

    /**
     * Feature key, unique across the registry, for example classroom.
     *
     * @return string
     */
    public function feature(): string;

    /**
     * Owning plugin, for example local_sentientia_classroom.
     *
     * @return string
     */
    public function component(): string;

    /**
     * Plugin version that carries the schema additions this importer writes to.
     *
     * @return int
     */
    public function requires_version(): int;

    /**
     * Feature keys that must be complete or not_applicable first.
     *
     * @return string[]
     */
    public function depends(): array;

    /**
     * Legacy tables this feature claims (one owner per table).
     *
     * @return array<string, source_spec> table => spec
     */
    public function sources(): array;

    /**
     * Legacy tables deliberately not imported, with the reason.
     *
     * @return array<string, string> legacy table => why
     */
    public function declined_tables(): array;

    /**
     * The only tables the writer will touch for this feature.
     *
     * @return string[]
     */
    public function target_tables(): array;

    /**
     * Core tables the feature may write, each with its reviewed reason
     * (for example tag_instance remap).
     *
     * @return array<string, string> core table => reviewed reason
     */
    public function core_writes(): array;

    /**
     * Path or root column of each target table, for the generic tenant verify.
     *
     * @return array<string, string> target table => path/root column
     */
    public function tenant_columns(): array;

    /**
     * The only codes skip, merge, fold and archive may use.
     *
     * @return reason[]
     */
    public function reasons(): array;

    /**
     * Owner choices, read from the decisions file.
     *
     * @return decision[]
     */
    public function decisions(): array;

    /**
     * True: run the whole feature in one outer transaction when its preflight
     * total is at or below --atomic-threshold.
     *
     * @return bool
     */
    public function atomic(): bool;

    /**
     * Load steps, then recompute steps, in execution order.
     *
     * @return array<step|recompute_step>
     */
    public function steps(): array;

    /**
     * Read-only checks. Blockers stop the whole run; warnings go to the report.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight;

    /**
     * Read-only, after load and recompute.
     *
     * @param context $ctx
     * @return string[] Failure lines; empty means pass.
     */
    public function verify(context $ctx): array;

    /**
     * Outside any transaction and idempotent: sequence resets, file copies,
     * cache purges. The runner writes the completion marker after this returns.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void;
}

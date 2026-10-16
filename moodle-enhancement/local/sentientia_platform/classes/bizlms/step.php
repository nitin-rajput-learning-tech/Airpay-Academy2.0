<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * One load step of an importer: one legacy source table (the accounting unit)
 * mapped to one primary target table (ADR-032, "Importer interface").
 *
 * transform() is PURE. It receives the rows of one group, ordered by id, and
 * returns outcomes. It performs no DB writes, never opens a recordset, and reads
 * only through the context. A dry run and an apply run execute the same code.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class step {

    /**
     * Step key, unique across the registry, prefixed by the feature
     * (for example classroom.attendance).
     *
     * @return string
     */
    abstract public function key(): string;

    /**
     * The accounting unit: a legacy table name, or for a derived group source a
     * hash-prefixed name such as #local_biz_cart_history.identifier.
     *
     * @return string
     */
    abstract public function sourcetable(): string;

    /**
     * The primary target table.
     *
     * @return string
     */
    abstract public function targettable(): string;

    /**
     * PRESERVE keeps legacy ids; MAP (the default) assigns new ones.
     *
     * @return string idpolicy::MAP or idpolicy::PRESERVE
     */
    public function idpolicy(): string {
        return idpolicy::MAP;
    }

    /**
     * Rows the import does NOT rewrite that hold this source's ids. Required for
     * a PRESERVE step, forbidden on a MAP step.
     *
     * @return array<array{0: string, 1: string, 2?: string}> [table, column, where]
     */
    public function external_refs(): array {
        return [];
    }

    /**
     * Sentientia tables that name this PRESERVE step's target rows by id, and the column that holds the id
     * (opt-in; evaluation item 7). Forbidden on a MAP step.
     *
     * PRESERVE keeps the legacy id, so a row already sitting in a child table under an id the legacy source holds, and
     * that the target does not hold yet (left by a rehearsal, or by an old delete that never cleared its children),
     * would attach to the parent the import is about to create: it would show as imported history, or collide with a
     * unique key and roll the whole feature back. Preflight counts those rows and blocks, naming the child table and
     * the count only (leftover_rows_at_legacy_ids:<step>:<table>:<n>). An id the target already holds is the
     * collision check's business, not this one.
     *
     * @return array<array{0: string, 1: string}> [child table, column holding the parent id]
     */
    public function target_children(): array {
        return [];
    }

    /**
     * Columns that identify an adoptable header copy: a target row at the legacy
     * id is adopted only when every listed column equals the source.
     *
     * An integer key names a column present in both tables; a string key maps a
     * source column to a differently named target column.
     *
     * @return array<int|string, string>
     */
    public function adopt_signature(): array {
        return ['name', 'timecreated'];
    }

    /**
     * Source columns forming one dedupe or fold group; empty means one row per group.
     *
     * @return string[]
     */
    public function group_by(): array {
        return [];
    }

    /**
     * Source columns to read. The default reads all. Names the source may not
     * have must be listed in the importer's source_spec::$optionalcolumns.
     *
     * @return string[]
     */
    public function columns(): array {
        return ['*'];
    }

    /**
     * Extra WHERE on the source, as portable SQL with named parameters. Every
     * framework query aliases the source table as t, so columns may be written
     * bare (status = 1) or qualified (t.status = 1).
     *
     * @return array{0: string, 1: array} [sql, params]
     */
    public function source_filter(): array {
        return ['', []];
    }

    /**
     * Map pairs to preload before transforming, as [sourcetable, subkey] pairs.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [];
    }

    /**
     * The physical legacy table behind sourcetable().
     *
     * @return string
     */
    final public function physical_table(): string {
        $name = ltrim($this->sourcetable(), '#');
        $dot = strpos($name, '.');
        return $dot === false ? $name : substr($name, 0, $dot);
    }

    /**
     * Is the accounting unit a derived group rather than a source row?
     *
     * @return bool
     */
    final public function is_derived(): bool {
        return strncmp($this->sourcetable(), '#', 1) === 0;
    }

    /**
     * PURE. The rows of ONE group (ordered by id) in, outcomes out.
     *
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    abstract public function transform(array $rows, context $ctx): array;
}

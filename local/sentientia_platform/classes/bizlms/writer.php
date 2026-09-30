<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The only code of the BizLMS import that writes (ADR-032, "Writing rules").
 *
 * Importers and steps have no write API: they return outcomes. The runner hands
 * those to this class, which enforces:
 *
 * 1. Declared tables only: the importer's target_tables() plus its reviewed
 *    core_writes(). A legacy table is never writable.
 * 2. No silent column loss: a field that is not a column of the target is
 *    refused. (import_record() would silently skip it, which is how
 *    migrate_all.php lost columns.)
 * 3. No strict-mode aborts inside a batch: a missing value for a NOT NULL
 *    column with no default, a char longer than the column, and a non-integer
 *    or out-of-range value for an integer column are refused before the query.
 * 4. Source timestamps are kept: every integer or number column named time*
 *    must be set explicitly.
 * 5. PRESERVE writes use import_record() with the legacy id, and the writer
 *    asserts it. MAP writes use insert_record().
 *
 * The same class writes the framework's own bookkeeping (map, run, step rows
 * and the completion markers), so a static scan can ban every $DB write method
 * outside this file. In a dry run every write method refuses.
 *
 * Messages name tables and columns, never values.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class writer {

    /** Plugin that owns the framework config keys. */
    public const COMPONENT = 'local_sentientia_platform';

    /** Rows per bulk insert of map rows. */
    private const MAP_CHUNK = 500;

    /** @var bool */
    private bool $dryrun;

    /** @var array<string, bool> Declared target tables. */
    private array $targets = [];

    /** @var array<string, string> Declared core tables => reviewed reason. */
    private array $core = [];

    /** @var array<string, bool> Tables a PRESERVE step of the importer writes: only import_preserved() adds rows to them. */
    private array $preserve = [];

    /** @var array<string, \database_column_info[]> */
    private array $columns = [];

    /**
     * @param bool $dryrun Every write method refuses when true.
     */
    public function __construct(bool $dryrun = false) {
        $this->dryrun = $dryrun;
    }

    /**
     * A writer restricted to one importer's declared tables.
     *
     * @param importer $importer
     * @return self
     */
    public function for_importer(importer $importer): self {
        $clone = clone $this;
        $clone->targets = array_fill_keys($importer->target_tables(), true);
        $clone->core = $importer->core_writes();
        $clone->preserve = [];
        foreach ($importer->steps() as $step) {
            if ($step instanceof step && $step->idpolicy() === idpolicy::PRESERVE) {
                $clone->preserve[$step->targettable()] = true;
            }
        }
        return $clone;
    }

    /**
     * @return bool
     */
    public function is_dryrun(): bool {
        return $this->dryrun;
    }

    /**
     * Run the writer's checks on a row without writing it. A dry run calls this,
     * so a row the writer would refuse in an apply run fails in the dry run too.
     *
     * @param string $table
     * @param \stdClass $row
     * @param bool $partial True for an update: only the given fields are checked.
     * @param bool $mapinsert True when the row is a MAP insert, which may not carry an id
     *        (insert() refuses it, so the dry run must too).
     * @return void
     * @throws writer_refused
     */
    public function check(string $table, \stdClass $row, bool $partial = false, bool $mapinsert = false): void {
        $this->assert_declared($table);
        if ($mapinsert && property_exists($row, 'id')) {
            throw new writer_refused("id_not_allowed_for_map_insert:{$table}");
        }
        if ($mapinsert) {
            $this->assert_not_preserved($table);
        }
        $this->validate($table, $row, $partial, true);
    }

    /**
     * Refuse a table the importer did not declare. The runner calls it for a fold
     * target, which no write ever touches.
     *
     * @param string $table
     * @return void
     * @throws writer_refused
     */
    public function check_table(string $table): void {
        $this->assert_declared($table);
    }

    // Target rows, for the importer that declared the tables.

    /**
     * Insert a row with a new id (MAP).
     *
     * @param string $table
     * @param \stdClass $row
     * @return int The new id.
     */
    public function insert(string $table, \stdClass $row): int {
        global $DB;
        $this->assert_live();
        $this->assert_declared($table);
        if (property_exists($row, 'id')) {
            throw new writer_refused("id_not_allowed_for_map_insert:{$table}");
        }
        $this->assert_not_preserved($table);
        $this->validate($table, $row, false);
        return (int) $DB->insert_record($table, $row);
    }

    /**
     * Insert a row that keeps its legacy id (PRESERVE).
     *
     * @param string $table
     * @param \stdClass $row Must not carry an id; it is set to the legacy id.
     * @param int $legacyid
     * @return int The id (equal to $legacyid).
     */
    public function import_preserved(string $table, \stdClass $row, int $legacyid): int {
        global $DB;
        $this->assert_live();
        if (!isset($this->targets[$table])) {
            throw new writer_refused("preserve_needs_a_declared_target:{$table}");
        }
        $this->assert_declared($table);
        if ($legacyid <= 0) {
            throw new writer_refused("preserve_needs_a_positive_legacy_id:{$table}");
        }
        if (property_exists($row, 'id') && (int) $row->id !== $legacyid) {
            throw new writer_refused("preserve_id_mismatch:{$table}");
        }
        $this->validate($table, $row, false);
        $row->id = $legacyid;
        $DB->import_record($table, $row);
        if ((int) $row->id !== $legacyid) {
            throw new writer_refused("preserve_id_changed:{$table}");
        }
        return $legacyid;
    }

    /**
     * Overwrite an adopted target row with the full mapping.
     *
     * @param string $table
     * @param int $targetid
     * @param \stdClass $row
     * @return void
     */
    public function adopt(string $table, int $targetid, \stdClass $row): void {
        global $DB;
        $this->assert_live();
        $this->assert_declared($table);
        $this->validate($table, $row, false, true);
        $row->id = $targetid;
        $DB->update_record($table, $row);
    }

    /**
     * Change a target row the import created or adopted (recompute steps).
     *
     * @param string $table
     * @param int $targetid
     * @param \stdClass $fields Only the columns to change.
     * @return void
     */
    public function update_own(string $table, int $targetid, \stdClass $fields): void {
        global $DB;
        $this->assert_live();
        $this->assert_declared($table);
        if (!provenance::is_imported($table, $targetid)) {
            throw new writer_refused("update_of_a_row_the_import_did_not_create:{$table}");
        }
        $this->validate($table, $fields, true, true);
        $fields->id = $targetid;
        $DB->update_record($table, $fields);
    }

    /**
     * Change an existing row of a reviewed core table (for example a tag_instance remap).
     *
     * @param string $table
     * @param int $targetid
     * @param \stdClass $fields
     * @return void
     */
    public function update_core(string $table, int $targetid, \stdClass $fields): void {
        global $DB;
        $this->assert_live();
        if (!isset($this->core[$table])) {
            throw new writer_refused("not_a_reviewed_core_write:{$table}");
        }
        $this->assert_declared($table);
        $this->validate($table, $fields, true, true);
        $fields->id = $targetid;
        $DB->update_record($table, $fields);
    }

    /**
     * Reset a PRESERVE target's sequence. DDL on MySQL, so it never runs
     * inside a transaction: the runner calls it from finalise() only.
     *
     * @param string $table
     * @return void
     */
    public function reset_sequence(string $table): void {
        global $DB;
        $this->assert_live();
        if (!isset($this->targets[$table])) {
            throw new writer_refused("reset_sequence_needs_a_declared_target:{$table}");
        }
        if ($DB->is_transaction_started()) {
            throw new writer_refused('reset_sequence_inside_a_transaction');
        }
        $DB->get_manager()->reset_sequence($table);
    }

    /**
     * Delete rows the import created (rehearsal purge only).
     *
     * @param string $table
     * @param int[] $ids
     * @return void
     */
    public function purge_rows(string $table, array $ids): void {
        global $DB;
        $this->assert_live();
        $this->assert_declared($table);
        foreach (array_chunk(array_values($ids), 1000) as $chunk) {
            $DB->delete_records_list($table, 'id', $chunk);
        }
    }

    // Framework bookkeeping.

    /**
     * Insert map rows in bulk. Every row must have the same fields.
     *
     * @param \stdClass[] $rows
     * @return void
     */
    public function insert_map_rows(array $rows): void {
        global $DB;
        $this->assert_live();
        foreach (array_chunk($rows, self::MAP_CHUNK) as $chunk) {
            foreach ($chunk as $row) {
                $this->validate(legacymap::TABLE, $row, false);
            }
            $DB->insert_records(legacymap::TABLE, $chunk);
        }
    }

    /**
     * Change an existing map row (--retry-skipped moves a skipped row to its new outcome).
     *
     * @param int $id
     * @param \stdClass $fields
     * @return void
     */
    public function update_map_row(int $id, \stdClass $fields): void {
        global $DB;
        $this->assert_live();
        $this->validate(legacymap::TABLE, $fields, true, true);
        $fields->id = $id;
        $DB->update_record(legacymap::TABLE, $fields);
    }

    /**
     * Delete map rows of a feature (rehearsal purge only).
     *
     * @param string $feature
     * @return void
     */
    public function delete_map_rows(string $feature): void {
        global $DB;
        $this->assert_live();
        $DB->delete_records(legacymap::TABLE, ['feature' => $feature]);
    }

    /**
     * @param \stdClass $run
     * @return int New run id.
     */
    public function create_run(\stdClass $run): int {
        global $DB;
        $this->assert_live();
        $this->validate('local_sentientia_legacyrun', $run, false);
        return (int) $DB->insert_record('local_sentientia_legacyrun', $run);
    }

    /**
     * @param int $runid
     * @param \stdClass $fields
     * @return void
     */
    public function update_run(int $runid, \stdClass $fields): void {
        global $DB;
        $this->assert_live();
        $this->validate('local_sentientia_legacyrun', $fields, true, true);
        $fields->id = $runid;
        $DB->update_record('local_sentientia_legacyrun', $fields);
    }

    /**
     * @param \stdClass $step
     * @return int New step id.
     */
    public function create_step(\stdClass $step): int {
        global $DB;
        $this->assert_live();
        $this->validate('local_sentientia_legacystep', $step, false);
        return (int) $DB->insert_record('local_sentientia_legacystep', $step);
    }

    /**
     * @param int $stepid
     * @param \stdClass $fields
     * @return void
     */
    public function update_step(int $stepid, \stdClass $fields): void {
        global $DB;
        $this->assert_live();
        $this->validate('local_sentientia_legacystep', $fields, true, true);
        $fields->id = $stepid;
        $DB->update_record('local_sentientia_legacystep', $fields);
    }

    /**
     * Write the completion marker of a feature: a recorded fact used by the
     * status check, dependency ordering and legacymap::feature_complete().
     *
     * @param string $feature
     * @param int $runid
     * @return void
     */
    public function set_marker(string $feature, int $runid): void {
        $this->assert_live();
        set_config('bizlms_complete_' . $feature, $runid, self::COMPONENT);
    }

    /**
     * Remove the completion marker of a feature (rehearsal purge only).
     *
     * @param string $feature
     * @return void
     */
    public function clear_marker(string $feature): void {
        $this->assert_live();
        unset_config('bizlms_complete_' . $feature, self::COMPONENT);
    }

    /**
     * Record that a feature tripped the side-effect tripwire in a run. It sticks until a purge, an operator's
     * acknowledgement in a rehearsal, or a restore, because the rows the run committed would otherwise let a
     * plain re-apply complete the feature.
     *
     * @param string $feature
     * @param int $runid
     * @return void
     */
    public function set_tripped(string $feature, int $runid): void {
        $this->assert_live();
        set_config('bizlms_tripped_' . $feature, max(1, $runid), self::COMPONENT);
    }

    /**
     * Forget a recorded trip (a completed feature after an acknowledgement, or a rehearsal purge).
     *
     * @param string $feature
     * @return void
     */
    public function clear_tripped(string $feature): void {
        $this->assert_live();
        if (get_config(self::COMPONENT, 'bizlms_tripped_' . $feature) !== false) {
            unset_config('bizlms_tripped_' . $feature, self::COMPONENT);
        }
    }

    /**
     * Set a framework config value (for example disarming the import guard).
     *
     * @param string $name
     * @param string|int $value
     * @return void
     */
    public function set_framework_config(string $name, string|int $value): void {
        $this->assert_live();
        set_config($name, $value, self::COMPONENT);
    }

    // Validation.

    /**
     * Refuse any write in a dry run.
     *
     * @return void
     */
    private function assert_live(): void {
        if ($this->dryrun) {
            throw new writer_refused('write_during_a_dry_run');
        }
    }

    /**
     * Refuse a table the importer did not declare, and any legacy table.
     *
     * @param string $table
     * @return void
     */
    private function assert_declared(string $table): void {
        if (in_array($table, legacy_tables::KNOWN, true)) {
            throw new writer_refused("legacy_table_is_read_only:{$table}");
        }
        if (!isset($this->targets[$table]) && !isset($this->core[$table])) {
            throw new writer_refused("undeclared_table:{$table}");
        }
    }

    /**
     * Refuse a MAP insert into a table a PRESERVE step of the importer owns. On MySQL and MariaDB an insert with
     * an explicit id raises AUTO_INCREMENT, and insert_record() takes max(id)+1: a MAP row would take a legacy id
     * the PRESERVE step has not written yet, and its import_record() would fail on the duplicate key.
     *
     * @param string $table
     * @return void
     */
    private function assert_not_preserved(string $table): void {
        if (isset($this->preserve[$table])) {
            throw new writer_refused("map_insert_into_a_preserve_table:{$table}");
        }
    }

    /**
     * Check a row against the live columns of its table.
     *
     * @param string $table
     * @param \stdClass $row
     * @param bool $partial True for an update: only the given fields are checked.
     * @param bool $idallowed True when an id field may be present (it is ignored).
     * @return void
     */
    private function validate(string $table, \stdClass $row, bool $partial, bool $idallowed = false): void {
        $columns = $this->columns_of($table);
        $fields = get_object_vars($row);

        foreach ($fields as $name => $value) {
            if ($name === 'id' && $idallowed) {
                continue;
            }
            if (!isset($columns[$name])) {
                throw new writer_refused("unknown_field:{$table}.{$name}");
            }
            $this->validate_value($table, $columns[$name], $value);
        }

        if ($partial) {
            return;
        }
        foreach ($columns as $name => $column) {
            if ($name === 'id' || array_key_exists($name, $fields)) {
                continue;
            }
            if (self::is_timestamp_column($column)) {
                throw new writer_refused("missing_timestamp:{$table}.{$name}");
            }
            if ($column->not_null && !$column->has_default) {
                throw new writer_refused("missing_required:{$table}.{$name}");
            }
        }
    }

    /**
     * @param string $table
     * @param \database_column_info $column
     * @param mixed $value
     * @return void
     */
    private function validate_value(string $table, \database_column_info $column, mixed $value): void {
        $where = $table . '.' . $column->name;
        if ($value === null) {
            if ($column->not_null) {
                throw new writer_refused("null_in_not_null:{$where}");
            }
            return;
        }
        switch ($column->meta_type) {
            case 'R':
            case 'I':
                if (is_bool($value) || is_float($value) || !is_scalar($value)
                        || !preg_match('/^-?[0-9]+$/', (string) $value)) {
                    throw new writer_refused("not_an_integer:{$where}");
                }
                $limit = self::limit_for($column);
                if ($limit !== null && abs((int) $value) > $limit) {
                    throw new writer_refused("integer_out_of_range:{$where}");
                }
                break;
            case 'N':
            case 'F':
                if (is_bool($value) || !is_numeric($value)) {
                    throw new writer_refused("not_a_number:{$where}");
                }
                break;
            case 'C':
                if (!is_scalar($value) || is_bool($value)) {
                    throw new writer_refused("not_a_string:{$where}");
                }
                if (\core_text::strlen((string) $value) > (int) $column->max_length) {
                    throw new writer_refused("too_long:{$where}");
                }
                if (!mb_check_encoding((string) $value, 'UTF-8')) {
                    throw new writer_refused("invalid_utf8:{$where}");
                }
                break;
            default:
                if (!is_scalar($value) || is_bool($value)) {
                    throw new writer_refused("not_a_string:{$where}");
                }
                if ($column->meta_type === 'X' && !mb_check_encoding((string) $value, 'UTF-8')) {
                    throw new writer_refused("invalid_utf8:{$where}");
                }
        }
    }

    /**
     * Largest absolute value an integer column is sure to hold, read from its
     * native type where the driver reports one.
     *
     * MySQL 8.0.19 and later report tinyint, smallint and int WITHOUT a display
     * width, so max_length is then numeric_precision (3, 5, 10) and no longer says
     * how wide the column is. The type name is the reliable signal; the width
     * buckets are the fallback for drivers that only report a width.
     *
     * @param \database_column_info $column
     * @return int|null Null when the column is a bigint and the check cannot matter.
     */
    private static function limit_for(\database_column_info $column): ?int {
        $type = strtolower((string) preg_replace('/\(.*$/', '', (string) $column->type));
        switch ($type) {
            case 'tinyint':
            case 'int1':
                return 127;
            case 'smallint':
            case 'int2':
                return 32767;
            case 'mediumint':
                return 8388607;
            case 'int':
            case 'integer':
            case 'int4':
                return 2147483647;
            case 'bigint':
            case 'int8':
                return null;
        }
        return self::int_limit((int) $column->max_length);
    }

    /**
     * Is this a time* column that must be set explicitly (integer or number only,
     * so a char column such as timezone is not caught)?
     *
     * @param \database_column_info $column
     * @return bool
     */
    private static function is_timestamp_column(\database_column_info $column): bool {
        return strncmp($column->name, 'time', 4) === 0 && in_array($column->meta_type, ['I', 'N', 'F'], true);
    }

    /**
     * Largest absolute value an XMLDB integer of this length is sure to hold
     * on every supported engine (Moodle maps length to tinyint, smallint,
     * mediumint, int and bigint).
     *
     * @param int $length
     * @return int|null Null when the column is wide enough not to matter.
     */
    private static function int_limit(int $length): ?int {
        if ($length <= 2) {
            return 127;
        }
        if ($length <= 4) {
            return 32767;
        }
        if ($length <= 6) {
            return 8388607;
        }
        if ($length <= 9) {
            return 2147483647;
        }
        return null;
    }

    /**
     * @param string $table
     * @return \database_column_info[] Keyed by column name.
     */
    private function columns_of(string $table): array {
        global $DB;
        if (!isset($this->columns[$table])) {
            $columns = $DB->get_columns($table);
            if (!$columns) {
                throw new writer_refused("unknown_table:{$table}");
            }
            $this->columns[$table] = $columns;
        }
        return $this->columns[$table];
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Bounded, read-only access to legacy tables (ADR-032, "Reading and performance").
 *
 * On MySQL get_recordset_sql() buffers the whole result, so the framework owns
 * every source read and pages it by keyset (WHERE id > :watermark ORDER BY id
 * with a LIMIT). Steps never open recordsets and never run an unbounded query:
 * they call page(), fetch() or the framework does it for them.
 *
 * Every table and column name is validated before it is placed in SQL.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_reader {

    /** Largest page a caller may ask for. */
    public const MAX_PAGE = 10000;

    /** Ids per IN() list. */
    private const IN_CHUNK = 1000;

    /** @var array<string, bool> */
    private array $exists = [];

    /**
     * Does the table exist?
     *
     * @param string $table Name without prefix.
     * @return bool
     */
    public function exists(string $table): bool {
        global $DB;
        fingerprint::assert_identifier($table);
        if (!array_key_exists($table, $this->exists)) {
            $this->exists[$table] = $DB->get_manager()->table_exists($table);
        }
        return $this->exists[$table];
    }

    /**
     * Column names of a table, in table order.
     *
     * @param string $table
     * @return string[]
     */
    public function columns(string $table): array {
        global $DB;
        fingerprint::assert_identifier($table);
        return array_keys($DB->get_columns($table));
    }

    /**
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function has_column(string $table, string $column): bool {
        return in_array($column, $this->columns($table), true);
    }

    /**
     * Row count, optionally restricted by a step filter.
     *
     * @param string $table
     * @param array{0: string, 1: array} $filter
     * @return int
     */
    public function count(string $table, array $filter = ['', []]): int {
        global $DB;
        fingerprint::assert_identifier($table);
        [$where, $params] = fingerprint::where($filter);
        return (int) $DB->get_field_sql('SELECT COUNT(1) FROM {' . $table . "} t {$where}", $params);
    }

    /**
     * How many groups the DATABASE sees when it groups on these columns. The
     * database compares with the column's collation (case, accents and trailing
     * spaces may not count), so a grouped step checks that its PHP grouping found
     * no more groups than this.
     *
     * @param string $table
     * @param string[] $groupcolumns
     * @param array{0: string, 1: array} $filter
     * @return int
     */
    public function count_groups(string $table, array $groupcolumns, array $filter = ['', []]): int {
        global $DB;
        fingerprint::assert_identifier($table);
        $columns = [];
        foreach ($groupcolumns as $column) {
            fingerprint::assert_identifier($column);
            $columns[] = 't.' . $column;
        }
        [$where, $params] = fingerprint::where($filter);
        return (int) $DB->get_field_sql('SELECT COUNT(1) FROM (SELECT 1 AS g FROM {' . $table . "} t {$where} GROUP BY "
            . implode(', ', $columns) . ') gcnt', $params);
    }

    /**
     * Highest id, optionally restricted by a step filter.
     *
     * @param string $table
     * @param array{0: string, 1: array} $filter
     * @return int 0 when empty.
     */
    public function max_id(string $table, array $filter = ['', []]): int {
        global $DB;
        fingerprint::assert_identifier($table);
        [$where, $params] = fingerprint::where($filter);
        return (int) $DB->get_field_sql('SELECT MAX(t.id) FROM {' . $table . "} t {$where}", $params);
    }

    /**
     * The id the table would hand out to its next row: its AUTO_INCREMENT counter (EV-26).
     *
     * A PRESERVE import keeps BizLMS ids, and rows elsewhere still point at ids BizLMS had issued, so the Sentientia
     * table must never hand a native row an id the legacy table has ever issued. MAX(id) misses the ids of rows
     * BizLMS hard-deleted (the highest one may be gone), but the counter does not: mysqldump keeps it, so a restored
     * copy still knows every id it issued.
     *
     * MySQL and MariaDB: parsed from SHOW CREATE TABLE, because information_schema.TABLES.AUTO_INCREMENT is cached by
     * MySQL 8 (information_schema_stats_expiry, 24 hours by default) and can lag behind the restore. Postgres: the
     * serial sequence's last_value. Anything else, or anything that cannot be read, is null: the caller falls back to
     * the table's highest id and says so.
     *
     * @param string $table Name without prefix.
     * @return int|null The next id (at least 1), or null when the counter cannot be read.
     */
    public function next_id(string $table): ?int {
        global $DB;
        fingerprint::assert_identifier($table);
        if (!$this->exists($table)) {
            return null;
        }
        try {
            switch ($DB->get_dbfamily()) {
                case 'mysql':
                    $row = $DB->get_record_sql('SHOW CREATE TABLE {' . $table . '}');
                    foreach ($row ? array_values((array) $row) : [] as $value) {
                        if (is_string($value) && stripos(ltrim($value), 'CREATE TABLE') === 0) {
                            return self::counter_from_create_table($value);
                        }
                    }
                    return null;
                case 'postgres':
                    $sequence = $DB->get_field_sql('SELECT pg_get_serial_sequence(:t, :c)',
                        ['t' => $DB->get_prefix() . $table, 'c' => 'id']);
                    // The name comes from the database itself; it is still checked before it is put in SQL.
                    if (!is_string($sequence) || !preg_match('/^[A-Za-z0-9_".]+$/', $sequence)) {
                        return null;
                    }
                    $state = $DB->get_record_sql('SELECT last_value, is_called FROM ' . $sequence);
                    if (!$state) {
                        return null;
                    }
                    $called = in_array($state->is_called, [true, 1, '1', 't', 'true'], true);
                    return max(1, (int) $state->last_value + ($called ? 1 : 0));
                default:
                    return null;
            }
        } catch (\dml_exception $e) {
            // A table the account may not describe, a driver that answers differently: unreadable, not fatal.
            return null;
        }
    }

    /**
     * The AUTO_INCREMENT counter in the text of a MySQL or MariaDB CREATE TABLE statement.
     *
     * Only the table options (the line that closes the column list, ") ENGINE=...") are searched, and quoted text in
     * them is removed first, so a column comment or a table COMMENT that happens to say AUTO_INCREMENT=9 is never
     * read as the counter. A table whose counter is 1 has no AUTO_INCREMENT clause, which is the answer 1, not an
     * unreadable counter. A statement with no auto-increment column at all is not one this can answer for.
     *
     * @param string $ddl
     * @return int|null The next id, or null when the statement is not a CREATE TABLE of a table with an
     *         auto-increment id.
     */
    public static function counter_from_create_table(string $ddl): ?int {
        if (stripos(ltrim($ddl), 'CREATE TABLE') !== 0 || stripos($ddl, 'AUTO_INCREMENT') === false) {
            return null;
        }
        // The options line closes the column list. Take the last line that starts with ")": a column definition
        // never does.
        $options = null;
        foreach (preg_split('/\R/', $ddl) as $line) {
            if (isset($line[0]) && $line[0] === ')') {
                $options = $line;
            }
        }
        if ($options === null) {
            return null;
        }
        $options = (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", "''", $options);
        if (preg_match('/\bAUTO_INCREMENT\s*=\s*(\d+)/i', $options, $match)) {
            return max(1, (int) $match[1]);
        }
        return 1;
    }

    /**
     * One keyset page: rows with id greater than $afterid, ascending.
     *
     * @param string $table
     * @param int $afterid Watermark.
     * @param int $limit At most MAX_PAGE.
     * @param string[] $columns Columns to read; ['*'] reads all. Names the table lacks are dropped.
     * @param array{0: string, 1: array} $filter
     * @return array<int, \stdClass> id => row
     */
    public function page(string $table, int $afterid, int $limit, array $columns = ['*'], array $filter = ['', []]): array {
        global $DB;
        $limit = max(1, min($limit, self::MAX_PAGE));
        $select = $this->select_list($table, $columns);
        [$extra, $params] = $this->and_filter($filter);
        $params['blmafter'] = $afterid;
        $rows = $DB->get_records_sql(
            "SELECT {$select} FROM {" . $table . "} t WHERE t.id > :blmafter {$extra} ORDER BY t.id ASC",
            $params, 0, $limit);
        return $this->by_id($table, $rows);
    }

    /**
     * Rows by id, in chunks so the IN() list stays short.
     *
     * @param string $table
     * @param int[] $ids
     * @param string[] $columns
     * @return array<int, \stdClass> id => row, ascending; ids that do not exist are absent.
     */
    public function fetch(string $table, array $ids, array $columns = ['*']): array {
        global $DB;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        if (!$ids) {
            return [];
        }
        $select = $this->select_list($table, $columns);
        $out = [];
        foreach (array_chunk($ids, self::IN_CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'blmid');
            $rows = $DB->get_records_sql(
                "SELECT {$select} FROM {" . $table . "} t WHERE t.id {$insql} ORDER BY t.id ASC", $params);
            foreach ($this->by_id($table, $rows) as $id => $row) {
                $out[$id] = $row;
            }
        }
        return $out;
    }

    /**
     * One keyset page of the group columns only, for the first phase of a
     * grouped step (scan id plus group columns, then process groups by minimum id).
     *
     * @param string $table
     * @param string[] $groupcolumns
     * @param int $afterid
     * @param int $limit
     * @param array{0: string, 1: array} $filter
     * @return array<int, \stdClass> id => row carrying id and the group columns
     */
    public function group_page(string $table, array $groupcolumns, int $afterid, int $limit, array $filter = ['', []]): array {
        return $this->page($table, $afterid, $limit, $groupcolumns, $filter);
    }

    /**
     * Build the SELECT list, always starting with id.
     *
     * get_records_sql() keys its result by the FIRST column. A production-only table can list its columns in
     * any order, so t.* alone could key the rows by something else and silently merge rows that share that
     * value, before by_id() ever sees them. The id goes first, explicitly; the repeated id column inside t.*
     * carries the same value.
     *
     * @param string $table
     * @param string[] $columns
     * @return string
     * @throws blocked When the table has no id column.
     */
    private function select_list(string $table, array $columns): string {
        fingerprint::assert_identifier($table);
        $have = $this->columns($table);
        if (!in_array('id', $have, true)) {
            throw new blocked('no_id_column:' . $table);
        }
        if ($columns === ['*'] || $columns === []) {
            return 't.id, t.*';
        }
        $list = ['t.id'];
        foreach ($columns as $column) {
            fingerprint::assert_identifier($column);
            if ($column !== 'id' && in_array($column, $have, true)) {
                $list[] = 't.' . $column;
            }
        }
        return implode(', ', array_unique($list));
    }

    /**
     * @param array{0: string, 1: array} $filter
     * @return array{0: string, 1: array}
     */
    private function and_filter(array $filter): array {
        $sql = trim((string) ($filter[0] ?? ''));
        return $sql === '' ? ['', []] : ['AND (' . $sql . ')', (array) ($filter[1] ?? [])];
    }

    /**
     * Re-key rows by their integer id.
     *
     * @param string $table
     * @param \stdClass[] $rows
     * @return array<int, \stdClass>
     */
    private function by_id(string $table, array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row->id)) {
                throw new blocked('no_id_column:' . $table);
            }
            $out[(int) $row->id] = $row;
        }
        return $out;
    }
}

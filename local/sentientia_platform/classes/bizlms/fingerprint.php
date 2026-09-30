<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Two kinds of fingerprint the import uses (ADR-032).
 *
 * 1. install(): the 12-hex-character token an operator must pass as --confirm
 *    with --apply and --purge-feature. It is derived from the site URL, the
 *    database name and the table prefix, so a command copied from a rehearsal
 *    cannot run on production.
 * 2. table(): row count, MAX(id) and a CRC over all columns of a legacy table.
 *    It detects a source that changed between runs (resume refuses) and, in
 *    the parity check, a legacy table mutated after import.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fingerprint {

    /** Default cap on the rows a CRC may read (see table()). */
    public const CRC_MAX_ROWS = 2000000;

    /** Identifier pattern accepted in table and column names (prefix-less). */
    private const IDENTIFIER = '/^[A-Za-z][A-Za-z0-9_]*$/';

    /**
     * The install fingerprint: first 12 hex characters of
     * sha1(wwwroot|dbname|prefix).
     *
     * @return string
     */
    public static function install(): string {
        global $CFG;
        return substr(sha1($CFG->wwwroot . '|' . $CFG->dbname . '|' . $CFG->prefix), 0, 12);
    }

    /**
     * Count, max id and CRC of a table, optionally restricted by a filter.
     *
     * The CRC is SUM(CRC32(CONCAT_WS(...))) over every column sorted by name,
     * built like the parity checksums (NULL gets an explicit sentinel, because
     * CONCAT_WS skips NULLs). It is null on engines without CRC32, exactly as
     * migration_parity_check.php reports them, so a comparison on such an
     * engine cannot read as a clean pass.
     *
     * The CRC reads every row, so on a table larger than $crcmaxrows it is skipped
     * (null, like an engine without CRC32) and count plus max id stand alone. The
     * exact query took 2m28s on a 2.6M-row logstore on MariaDB 10.11, and a step
     * pays it on every dry run, apply and resume. Pass PHP_INT_MAX for no cap.
     *
     * @param string $table Table name without prefix.
     * @param array{0: string, 1: array} $filter [sql, params] restricting the rows.
     * @param int $crcmaxrows Skip the CRC above this many rows.
     * @return array{count: int, maxid: int, crc: ?string, columns: string[]}
     */
    public static function table(string $table, array $filter = ['', []], int $crcmaxrows = self::CRC_MAX_ROWS): array {
        global $DB;

        self::assert_identifier($table);
        [$where, $params] = self::where($filter);
        $from = '{' . $table . '}';

        $count = (int) $DB->get_field_sql("SELECT COUNT(1) FROM {$from} t {$where}", $params);
        $maxid = (int) $DB->get_field_sql("SELECT MAX(t.id) FROM {$from} t {$where}", $params);

        $columns = array_keys($DB->get_columns($table));
        sort($columns);

        $crc = null;
        if ($DB->get_dbfamily() === 'mysql' && $columns && $count <= $crcmaxrows) {
            $parts = [];
            foreach ($columns as $column) {
                self::assert_identifier($column);
                $parts[] = "IFNULL(t.`{$column}`, '~NULL~')";
            }
            $expr = 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';
            $crc = (string) $DB->get_field_sql(
                "SELECT COALESCE(SUM(CRC32({$expr})), 0) FROM {$from} t {$where}", $params);
        }

        return ['count' => $count, 'maxid' => $maxid, 'crc' => $crc, 'columns' => $columns];
    }

    /**
     * Build a WHERE clause from a step filter.
     *
     * @param array{0: string, 1: array} $filter
     * @return array{0: string, 1: array}
     */
    public static function where(array $filter): array {
        $sql = trim((string) ($filter[0] ?? ''));
        $params = (array) ($filter[1] ?? []);
        if ($sql === '') {
            return ['', []];
        }
        return ['WHERE (' . $sql . ')', $params];
    }

    /**
     * Refuse anything that is not a plain identifier before it is placed in SQL.
     *
     * @param string $name
     * @return void
     */
    public static function assert_identifier(string $name): void {
        if (!preg_match(self::IDENTIFIER, $name)) {
            throw new \coding_exception('not a plain SQL identifier: ' . $name);
        }
    }
}

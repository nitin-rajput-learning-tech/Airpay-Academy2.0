<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Runtime guard that tells rows the import created from rows a person created
 * (ADR-032). Readers and "protect imported history" code fixes use it: a target
 * row is imported when the map holds an imported or adopted entry for it.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provenance {

    /**
     * SQL fragment matching target rows NOT created by the import.
     *
     * @param string $alias Alias of the target table in the caller's query ('' for none).
     * @param string $targettable Target table name without prefix.
     * @param string $tag Prefix for the map alias and the parameter name, so several
     *        fragments can live in one query.
     * @return array{0: string, 1: array} [sql, params]
     */
    public static function not_imported_sql(string $alias, string $targettable, string $tag = 'blm'): array {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $tag)) {
            throw new \coding_exception('provenance tag must be a plain identifier');
        }
        $idcolumn = $alias === '' ? 'id' : $alias . '.id';
        $sql = "NOT EXISTS (SELECT 1
                              FROM {" . legacymap::TABLE . "} {$tag}m
                             WHERE {$tag}m.targettable = :{$tag}table
                               AND {$tag}m.targetid = {$idcolumn}
                               AND {$tag}m.outcome IN ('imported', 'adopted'))";
        return [$sql, [$tag . 'table' => $targettable]];
    }

    /**
     * Did the import create or adopt this target row?
     *
     * @param string $targettable
     * @param int $targetid
     * @return bool
     */
    public static function is_imported(string $targettable, int $targetid): bool {
        global $DB;
        return $DB->record_exists_select(legacymap::TABLE,
            "targettable = :t AND targetid = :i AND outcome IN ('imported', 'adopted')",
            ['t' => $targettable, 'i' => $targetid]);
    }
}

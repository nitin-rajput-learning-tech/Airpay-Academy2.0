<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The unclaimed-table check (ADR-032, "Parity hooks" 3).
 *
 * Unclaimed = legacy tables in the database, minus every table an importer
 * claims or declines. It catches tables whose plugin code is missing from the
 * snapshot (local_challenge, local_positions, local_domains, block_request_*):
 * until an owner claims or declines them, parity exits 2 when they hold rows.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unclaimed {

    /**
     * Every table some importer claims or declines.
     *
     * @param importer[] $importers
     * @return array<string, string> table => feature that owns it
     */
    public static function owned(array $importers): array {
        $owned = [];
        foreach ($importers as $importer) {
            foreach (array_keys($importer->sources()) as $table) {
                $owned[$table] = $importer->feature();
            }
            foreach (array_keys($importer->declined_tables()) as $table) {
                $owned[$table] = $importer->feature();
            }
        }
        return $owned;
    }

    /**
     * Legacy tables no importer claims or declines.
     *
     * @param importer[] $importers
     * @return string[] Sorted table names without prefix.
     */
    public static function find(array $importers): array {
        $owned = self::owned($importers);
        $out = [];
        foreach (legacy_tables::detect() as $table) {
            if (!isset($owned[$table])) {
                $out[] = $table;
            }
        }
        return $out;
    }

    /**
     * Unclaimed legacy tables that hold at least one row.
     *
     * @param importer[] $importers
     * @return string[] Sorted table names without prefix.
     */
    public static function with_rows(array $importers): array {
        global $DB;
        $out = [];
        foreach (self::find($importers) as $table) {
            if ($DB->record_exists_sql('SELECT 1 FROM {' . $table . '}', [])) {
                $out[] = $table;
            }
        }
        return $out;
    }
}

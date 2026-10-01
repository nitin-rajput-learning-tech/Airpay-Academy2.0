<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;

/**
 * The parity oracle of the ratings import (mapping doc section 20, "Parity oracle").
 *
 * BizLMS kept a derived cache, local_ratings_likes, that it rewrote on every rating and like. It is not
 * imported, but what it showed learners (course tiles, and viewers who were not enrolled) is the yardstick for
 * what the imported averages should look like: they must agree within 0.05 stars, and each difference needs a
 * reason. The reasons the map names, found here from the rows themselves:
 *
 *   no_imported_ratings   the cache has a figure for an item the import brought no rating for
 *   skipped_rows          the item has legacy ratings that are not 1 to 5, which the import skips
 *   merged_duplicates     a learner had several rows for the item and the import kept one
 *   cache_count_stale     the cache's rating count is not the number of legacy rows (BizLMS's mobile likes
 *                         never updated it, and its two-decimal average ran over every row, not rating > 0)
 *
 * A difference none of these explains is "unexplained" and is the one to look at first.
 *
 * Everything here reads ids, counts and averages. It carries no personal data. compare() is pure so it can be
 * tested without a database; imported_vs_cache() and cache_vs_legacy() load the numbers.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class oracle {

    /** Largest difference, in stars, between the cache and an imported average that needs no explanation. */
    public const TOLERANCE = 0.05;

    /** Most items one comparison loads, so a table of unknown size cannot exhaust memory. */
    private const MAX_ITEMS = 100000;

    /**
     * Compare imported averages with the cache, item by item.
     *
     * @param array<string, array{avg: float, users: int}> $cache Cache figures by "area|item" (Sentientia names).
     * @param array<string, array{avg: float, count: int}> $imported Average over rating > 0 and the rating count.
     * @param array<string, array{total: int, valid: int}> $legacy Legacy rows per item and how many are 1 to 5.
     * @return array<int, array{key: string, cache: float, imported: ?float, because: string[]}> One entry per
     *         item that differs by more than the tolerance, with the reasons that explain it.
     */
    public static function compare(array $cache, array $imported, array $legacy): array {
        $differences = [];
        foreach ($cache as $key => $figure) {
            $key = (string) $key;
            $row = $imported[$key] ?? null;
            $shown = $row === null ? null : round((float) $row['avg'], 1);
            if ($shown !== null && abs((float) $figure['avg'] - $shown) <= self::TOLERANCE + 1e-9) {
                continue;
            }
            $rows = $legacy[$key] ?? ['total' => 0, 'valid' => 0];
            $because = [];
            if ($row === null) {
                $because[] = 'no_imported_ratings';
            }
            if ((int) $rows['total'] - (int) $rows['valid'] > 0) {
                $because[] = 'skipped_rows';
            }
            if ($row !== null && (int) $rows['valid'] > (int) $row['count']) {
                $because[] = 'merged_duplicates';
            }
            if ((int) $figure['users'] !== (int) $rows['total']) {
                $because[] = 'cache_count_stale';
            }
            $differences[] = [
                'key' => $key,
                'cache' => (float) $figure['avg'],
                'imported' => $shown,
                'because' => $because ?: ['unexplained'],
            ];
        }
        return $differences;
    }

    /**
     * After the import: the averages Sentientia now shows next to what the BizLMS cache showed.
     *
     * @return array{compared: int, differences: array, unexplained: int}
     */
    public static function imported_vs_cache(): array {
        global $DB;
        $map = new legacymap();

        $cache = [];
        foreach (self::cache_rows() as $row) {
            $key = self::target_key($map, (string) $row->module_area, (int) $row->module_id);
            if ($key !== null) {
                $cache[$key] = ['avg' => (float) $row->module_rating, 'users' => (int) $row->module_rating_users];
            }
        }

        $imported = [];
        $rows = $DB->get_records_sql(
            'SELECT MIN(id) AS id, ratearea, itemid, AVG(rating) AS a, COUNT(1) AS n
               FROM {local_sentientia_ratings}
              WHERE rating > 0
           GROUP BY ratearea, itemid', [], 0, self::MAX_ITEMS);
        foreach ($rows as $row) {
            $imported[$row->ratearea . '|' . (int) $row->itemid] = ['avg' => (float) $row->a, 'count' => (int) $row->n];
        }

        $legacy = [];
        if ($DB->get_manager()->table_exists('local_rating')) {
            $rows = $DB->get_records_sql(
                'SELECT MIN(id) AS id, ratearea, itemid, COUNT(1) AS total,
                        SUM(CASE WHEN rating IN (1, 2, 3, 4, 5) THEN 1 ELSE 0 END) AS valid
                   FROM {local_rating}
                  WHERE itemid IS NOT NULL AND ratearea IS NOT NULL
               GROUP BY ratearea, itemid', [], 0, self::MAX_ITEMS);
            foreach ($rows as $row) {
                $key = self::target_key($map, (string) $row->ratearea, (int) $row->itemid);
                if ($key !== null) {
                    $legacy[$key] = ['total' => (int) $row->total, 'valid' => (int) $row->valid];
                }
            }
        }

        $differences = self::compare($cache, $imported, $legacy);
        $unexplained = 0;
        foreach ($differences as $difference) {
            if ($difference['because'] === ['unexplained']) {
                $unexplained++;
            }
        }
        return ['compared' => count($cache), 'differences' => $differences, 'unexplained' => $unexplained];
    }

    /**
     * Before the import: how far the cache is from the legacy ratings themselves. The cache is only a yardstick if
     * it was right, so the preflight tells the owner how many items it already disagreed on.
     *
     * @return array{cache_rows: int, disagree: int}
     */
    public static function cache_vs_legacy(): array {
        global $DB;
        $out = ['cache_rows' => 0, 'disagree' => 0];
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_ratings_likes') || !$dbman->table_exists('local_rating')) {
            return $out;
        }

        // BizLMS averaged every row that had a rating, to two decimals.
        $raw = [];
        $rows = $DB->get_records_sql(
            'SELECT MIN(id) AS id, ratearea, itemid, AVG(rating) AS a
               FROM {local_rating}
              WHERE rating IS NOT NULL AND itemid IS NOT NULL AND ratearea IS NOT NULL
           GROUP BY ratearea, itemid', [], 0, self::MAX_ITEMS);
        foreach ($rows as $row) {
            $raw[$row->ratearea . '|' . (int) $row->itemid] = (float) $row->a;
        }
        foreach (self::cache_rows() as $row) {
            $out['cache_rows']++;
            $key = $row->module_area . '|' . (int) $row->module_id;
            if (!isset($raw[$key]) || abs((float) $row->module_rating - $raw[$key]) > self::TOLERANCE + 1e-9) {
                $out['disagree']++;
            }
        }
        return $out;
    }

    /**
     * The BizLMS cache rows that name an item.
     *
     * @return \stdClass[]
     */
    private static function cache_rows(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_ratings_likes')) {
            return [];
        }
        return array_values($DB->get_records_sql(
            'SELECT id, module_area, module_id, module_rating, module_rating_users
               FROM {local_ratings_likes}
              WHERE module_id IS NOT NULL AND module_area IS NOT NULL
           ORDER BY id', [], 0, self::MAX_ITEMS));
    }

    /**
     * The "area|item" key, in Sentientia's names, of a legacy (area, item) pair.
     *
     * @param legacymap $map
     * @param string $legacyarea
     * @param int $itemid
     * @return string|null Null when the area is not mapped or the item was not imported.
     */
    private static function target_key(legacymap $map, string $legacyarea, int $itemid): ?string {
        $area = area_map::target_area($legacyarea);
        $kind = area_map::parent_kind($legacyarea);
        if ($area === null || $kind === null || $itemid <= 0) {
            return null;
        }
        $item = $kind === area_map::COURSE ? $itemid : $map->resolve($kind, $itemid);
        return $item === null ? null : $area . '|' . $item;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * What the org importer knows about the whole of local_costcenter (ADR-032, mapping doc section 3).
 *
 * The table is small (tens of rows), and three things about one row depend on the others: where it ranks
 * among its siblings, whether its parent exists, and whether another row claims its path. So the step, the
 * preflight and verify all read the table through this one class, and all three get the same answer.
 *
 * Everything here is pure: it reads through the importer context (bounded, keyset-paged, read-only) and
 * writes nothing.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_source {

    /** The legacy table. */
    public const TABLE = 'local_costcenter';

    /** Gap between two sibling sort orders, so an admin can put an org between two without renumbering. */
    public const SORT_STEP = 10;

    /** Largest depth the importer believes; anything else is derived from the path. */
    private const MAX_DEPTH = 99;

    /** Rows per keyset page. */
    private const PAGE = 2000;

    /** Columns the analysis needs from every row. */
    private const COLUMNS = ['id', 'parentid', 'path', 'depth', 'sortorder'];

    /**
     * Every row of local_costcenter with the columns the analysis needs.
     *
     * @param context $ctx
     * @return array<int, \stdClass> id => row, ascending
     */
    public static function rows(context $ctx): array {
        $rows = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(self::TABLE, $after, self::PAGE, self::COLUMNS);
            foreach ($page as $id => $row) {
                $rows[(int) $id] = $row;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $rows;
    }

    /**
     * The normalised tenant path of a row, or null when the row has no usable path.
     *
     * @param \stdClass $row
     * @return string|null '/1/5' for ' 1/5/ ', null for '', '0' and a path with a non-numeric segment.
     */
    public static function path_of(\stdClass $row): ?string {
        return tenant_resolver::normalise(isset($row->path) ? (string) $row->path : null);
    }

    /**
     * The tenant root of a normalised path.
     *
     * @param string $path
     * @return int
     */
    public static function root_of(string $path): int {
        return (int) explode('/', ltrim($path, '/'))[0];
    }

    /**
     * Is this a registered tenant root? Delegates to tenant_resolver::root_is_registered() (2026-10-07, F-11), which
     * asks tenant::assert_valid() and so the tenant registry. VALID_TENANTS is never tested directly (mapping doc
     * rule R3). This importer is the TENANT_OWNER: it must not use tenant_resolver::resolve() for its own rows,
     * because resolve() checks a candidate against local_sentientia_org, the table this feature is filling.
     *
     * @param int $root
     * @return bool
     */
    public static function root_is_registered(int $root): bool {
        return tenant_resolver::root_is_registered($root);
    }

    /**
     * Is this stored value a valid tenant path, the way the framework's own tenant check reads it? It must BE a
     * normalised path (not merely normalise to one) whose root is a registered tenant. The path-boundary defect
     * class: readers compare the stored text, so ' /1/5' or '//1//5' would be scoped wrong.
     *
     * @param string $value
     * @return bool
     */
    public static function path_is_valid(string $value): bool {
        $path = tenant_resolver::normalise($value);
        return $path !== null && $path === $value && self::root_is_registered(self::root_of($path));
    }

    /**
     * The depth to store: the source depth when it is a sane number, else the number of path segments.
     *
     * @param \stdClass $row
     * @param string $path Normalised path of the row.
     * @return array{0: int, 1: string|null} [depth, warning code or null]
     */
    public static function depth_of(\stdClass $row, string $path): array {
        $segments = count(explode('/', ltrim($path, '/')));
        $depth = isset($row->depth) ? (int) $row->depth : 0;
        if ($depth < 1 || $depth > self::MAX_DEPTH) {
            return [$segments, 'derived_depth'];
        }
        return [$depth, $depth === $segments ? null : 'depth_differs_from_path'];
    }

    /**
     * Compare two BizLMS sort orders ("vancodes", '01', '01.01', '01.0a', '01.110').
     *
     * A vancode starts with a digit that is the length of its base-36 number minus one, so comparing two
     * codes as text orders them as numbers. A child's code is its parent's plus '.' plus its own, so it is
     * compared segment by segment. A missing or empty sort order goes last.
     *
     * @param string|null $a
     * @param string|null $b
     * @return int -1, 0 or 1
     */
    public static function compare_sortorder(?string $a, ?string $b): int {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a === '' || $b === '') {
            return $a === $b ? 0 : ($a === '' ? 1 : -1);
        }
        $as = explode('.', $a);
        $bs = explode('.', $b);
        $n = min(count($as), count($bs));
        for ($i = 0; $i < $n; $i++) {
            $cmp = strcmp($as[$i], $bs[$i]);
            if ($cmp !== 0) {
                return $cmp < 0 ? -1 : 1;
            }
        }
        return count($as) <=> count($bs);
    }

    /**
     * The integer sort order every org gets: its rank among its siblings times SORT_STEP.
     *
     * Siblings share a parentid (NULL counts as 0). They are ranked by their BizLMS sort order and, for a
     * tie or a missing one, by id, so the answer does not depend on the order the rows are read in.
     * Only rows with a usable path take part: the others are not organisations and are skipped.
     *
     * @param array<int, \stdClass> $rows As returned by rows().
     * @return array<int, int> org id => sort order
     */
    public static function sort_orders(array $rows): array {
        $siblings = [];
        foreach ($rows as $id => $row) {
            if (self::path_of($row) === null) {
                continue;
            }
            $siblings[(int) ($row->parentid ?? 0)][] = $row;
        }
        $out = [];
        foreach ($siblings as $group) {
            usort($group, static function (\stdClass $a, \stdClass $b): int {
                $cmp = self::compare_sortorder($a->sortorder ?? null, $b->sortorder ?? null);
                return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
            });
            foreach ($group as $rank => $row) {
                $out[(int) $row->id] = ($rank + 1) * self::SORT_STEP;
            }
        }
        return $out;
    }

    /**
     * What is odd about the table, as counts keyed by a code. None of it stops the import (the rows are the
     * owner's history, and the legacy table is frozen so there is nothing to correct it with); all of it
     * goes to the report, where someone can look.
     *
     * @param array<int, \stdClass> $rows As returned by rows().
     * @return array<string, int> code => number of rows
     */
    public static function anomalies(array $rows): array {
        $counts = [
            'not_org_row' => 0,
            'invalid_tenant_root' => 0,
            'duplicate_path' => 0,
            'path_id_mismatch' => 0,
            'orphan_parent' => 0,
            'path_not_under_parent' => 0,
            'derived_depth' => 0,
            'depth_differs_from_path' => 0,
        ];
        $paths = [];
        foreach ($rows as $row) {
            $path = self::path_of($row);
            if ($path !== null) {
                $paths[$path] = ($paths[$path] ?? 0) + 1;
            }
        }
        foreach ($rows as $id => $row) {
            $path = self::path_of($row);
            if ($path === null) {
                $counts['not_org_row']++;
                continue;
            }
            if (!self::root_is_registered(self::root_of($path))) {
                $counts['invalid_tenant_root']++;
                continue;
            }
            if ($paths[$path] > 1) {
                $counts['duplicate_path']++;
            }
            $segments = explode('/', ltrim($path, '/'));
            if ((int) end($segments) !== (int) $id) {
                $counts['path_id_mismatch']++;
            }
            $parentid = (int) ($row->parentid ?? 0);
            if ($parentid > 0) {
                $parent = $rows[$parentid] ?? null;
                $parentpath = $parent === null ? null : self::path_of($parent);
                if ($parent === null) {
                    $counts['orphan_parent']++;
                } else if ($parentpath === null || strncmp($path, $parentpath . '/', strlen($parentpath) + 1) !== 0) {
                    $counts['path_not_under_parent']++;
                }
            }
            [, $warning] = self::depth_of($row, $path);
            if ($warning !== null) {
                $counts[$warning]++;
            }
        }
        return array_filter($counts);
    }
}

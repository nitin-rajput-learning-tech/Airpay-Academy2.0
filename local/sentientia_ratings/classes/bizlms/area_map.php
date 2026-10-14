<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\reason;

/**
 * The area and item rules of the ratings import (mapping doc section 20, "Column maps").
 *
 * BizLMS stored what a rating, a review or a reaction was about as an area name plus an item id, in three
 * tables with the same two columns under different names (ratearea, commentarea, likearea). Sentientia keeps
 * the same pair but renamed the areas after the plugins that own the items, so every row goes through this
 * map once:
 *
 *   local_courses       -> local_sentientia_courses      item = a course id (core ids are not mapped)
 *   local_classroom     -> local_sentientia_classroom    item = the classroom's id through the legacy map
 *   local_program       -> local_sentientia_programs     item = the programme's id through the legacy map
 *   local_learningplan  -> local_sentientia_learningpath item = the learning plan's id through the legacy map
 *
 * Any other area (the certification area has no Sentientia entity yet) is skipped and reported as
 * unknown_area (decision ratings.certification_area = skip_and_report). An area longer than the target
 * column holds is skipped too: the full value stays in the legacy table.
 *
 * The three parent tables are resolved through the map even when their ids were kept, as ADR-032 requires, so
 * the importer depends on classroom, program and learningplan.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class area_map {

    /** Longest area the target columns hold (CHAR(100) on all three tables). */
    public const TARGET_MAX = 100;

    /** Parent kind of an item that is a core course id: nothing to map. */
    public const COURSE = 'course';

    /**
     * Legacy area => [Sentientia area, parent kind]. A parent kind other than COURSE names the legacy table whose
     * map row gives the item's new id.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const AREAS = [
        'local_courses' => ['local_sentientia_courses', self::COURSE],
        'local_classroom' => ['local_sentientia_classroom', 'local_classroom'],
        'local_program' => ['local_sentientia_programs', 'local_program'],
        'local_learningplan' => ['local_sentientia_learningpath', 'local_learningplan'],
    ];

    /**
     * The BizLMS area names that map to a Sentientia area.
     *
     * @return string[]
     */
    public static function legacy_areas(): array {
        return array_keys(self::AREAS);
    }

    /**
     * The Sentientia area names the import writes.
     *
     * @return string[]
     */
    public static function target_areas(): array {
        return array_column(self::AREAS, 0);
    }

    /**
     * The Sentientia area a BizLMS area becomes.
     *
     * @param string $legacy
     * @return string|null Null for an area that is not mapped.
     */
    public static function target_area(string $legacy): ?string {
        return self::AREAS[$legacy][0] ?? null;
    }

    /**
     * What an area's items are: COURSE for a core course id, otherwise the legacy table whose map gives the id.
     *
     * @param string $legacy
     * @return string|null Null for an area that is not mapped.
     */
    public static function parent_kind(string $legacy): ?string {
        return self::AREAS[$legacy][1] ?? null;
    }

    /**
     * The legacy tables whose map rows the item ids are resolved through.
     *
     * @return string[]
     */
    public static function parent_tables(): array {
        $tables = [];
        foreach (self::AREAS as $entry) {
            if ($entry[1] !== self::COURSE) {
                $tables[] = $entry[1];
            }
        }
        return $tables;
    }

    /**
     * Is this "area" a probe string and not a plugin-style name?
     *
     * A BizLMS area is a Moodle component name (local_courses, local_certification): letters, digits and underscores,
     * and at most a dot or a hyphen. Anything else (a quote, a bracket, a slash, white space, a percent escape, a
     * non-ASCII letter, a control character) is what a scanner sends and no plugin ever wrote. This only LABELS the
     * row in the report; the row is skipped as unknown_area either way.
     *
     * @param string $area
     * @return bool
     */
    public static function is_probe(string $area): bool {
        return $area !== '' && preg_match('/[^A-Za-z0-9_.\-]/', $area) === 1;
    }

    /**
     * The Sentientia item id and area of a legacy (item, area) pair, or why the row cannot be imported.
     *
     * @param context $ctx
     * @param mixed $itemid The legacy item id, as read (a string, an int or null).
     * @param mixed $area The legacy area, as read.
     * @return array{0: ?int, 1: ?string, 2: ?string, 3: string} [item, area, reason, detail]. The reason is null
     *         when the pair maps; the detail is a code (words joined by colons), never an id.
     */
    public static function resolve(context $ctx, mixed $itemid, mixed $area): array {
        $area = $area === null ? '' : (string) $area;
        if ($area === '') {
            return [null, null, 'unknown_area', 'no_area'];
        }
        if (!isset(self::AREAS[$area])) {
            // Owner decision CRS-10 / doc item "ratings security" (2026-10-07): BizLMS's like endpoint stored what an
            // unauthenticated caller sent, and the April 2026 copy holds 194 local_like rows whose area is a web
            // vulnerability scanner's probe string (injection and path-traversal payloads, 19 of them over 100
            // characters, from 2024-01 to 2025-12, none with a real user). An area is a plugin-style name; one with a
            // character such a name never holds is labelled as the scanner's, so the parity report shows what the
            // skipped rows are. The label is a detail code only: the value itself stays in the legacy table.
            if (self::is_probe($area)) {
                return [null, null, 'unknown_area', 'scanner_payload'];
            }
            if (\core_text::strlen($area) > self::TARGET_MAX) {
                return [null, null, 'unknown_area', 'area_too_long'];
            }
            return [null, null, 'unknown_area', $area === 'local_certification' ? 'certification_area' : 'area_not_mapped'];
        }
        [$target, $kind] = self::AREAS[$area];

        $id = ($itemid === null || $itemid === '') ? 0 : (int) $itemid;
        if ($id <= 0) {
            return [null, null, 'orphan_item', 'no_item'];
        }
        if ($kind === self::COURSE) {
            if (!$ctx->lookups->course_exists($id)) {
                return [null, null, 'orphan_item', 'course_not_found'];
            }
            return [$id, $target, null, ''];
        }

        $mapped = $ctx->map->resolve($kind, $id);
        if ($mapped === null) {
            // A single-feature dry run whose parent feature has not run cannot tell yet; an apply run always can.
            if ($ctx->is_deferred($kind)) {
                return [null, null, reason::DEFERRED, ''];
            }
            return [null, null, 'orphan_item', 'parent_not_imported'];
        }
        return [$mapped, $target, null, ''];
    }
}

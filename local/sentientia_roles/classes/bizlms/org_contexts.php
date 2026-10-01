<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * Where an organisation's role assignments live: the course category context of its category (ADR-032, org_roles).
 *
 * The link from an organisation to its category is the category column of local_costcenter, which stays in place
 * (the org feature leaves it and the readers use it directly). This class only READS. In particular it never calls
 * context_coursecat::instance(), because that creates a missing context row: a missing context is a preflight
 * blocker for the owner to look at, not something an import repairs.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_contexts {

    /** Ids per IN() list. */
    private const CHUNK = 1000;

    /**
     * The tenant root of a normalised organisation path.
     *
     * @param string|null $path For example /1/5.
     * @return int The first segment (1 for /1/5); 0 when there is no path.
     */
    public static function root_of(?string $path): int {
        if ($path === null || $path === '') {
            return 0;
        }
        return (int) explode('/', ltrim($path, '/'))[0];
    }

    /**
     * Is a path the ancestor itself or below it? Whole segments only: /1/20 is not inside /1/2.
     *
     * @param string $path A normalised path.
     * @param string $ancestor A normalised path.
     * @return bool
     */
    public static function path_within(string $path, string $ancestor): bool {
        return $path === $ancestor || str_starts_with($path, $ancestor . '/');
    }

    /**
     * The category and its context for each organisation.
     *
     * @param legacy_reader $legacy
     * @param int[] $orgids local_costcenter ids.
     * @return array<int, \stdClass> orgid => {category: int, contextid: ?int, path: ?string}. An organisation that is
     *         not a local_costcenter row is absent. category is 0 when the row has none, and contextid is null when
     *         the category has no context row.
     */
    public static function for_orgs(legacy_reader $legacy, array $orgids): array {
        global $DB;
        $orgids = array_values(array_unique(array_filter(array_map('intval', $orgids), static fn(int $id): bool => $id > 0)));
        if (!$orgids || !$legacy->exists(importer::COSTCENTER)) {
            return [];
        }

        $rows = $legacy->fetch(importer::COSTCENTER, $orgids, ['id', 'category', 'path']);
        $out = [];
        $categories = [];
        foreach ($rows as $id => $row) {
            $category = (int) ($row->category ?? 0);
            $out[(int) $id] = (object) [
                'category' => max(0, $category),
                'contextid' => null,
                'path' => tenant_resolver::normalise($row->path ?? null),
            ];
            if ($category > 0) {
                $categories[$category] = true;
            }
        }

        $contexts = [];
        foreach (array_chunk(array_keys($categories), self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'blmcat');
            $params['blmlevel'] = CONTEXT_COURSECAT;
            // The first column keys the result; instanceid is unique per context level.
            $found = $DB->get_records_sql(
                "SELECT ctx.instanceid AS id, ctx.id AS contextid
                   FROM {context} ctx
                  WHERE ctx.contextlevel = :blmlevel AND ctx.instanceid $insql", $params);
            foreach ($found as $instanceid => $context) {
                $contexts[(int) $instanceid] = (int) $context->contextid;
            }
        }
        foreach ($out as $info) {
            if ($info->category > 0) {
                $info->contextid = $contexts[$info->category] ?? null;
            }
        }
        return $out;
    }
}

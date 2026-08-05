<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for the skill -> business-impact mapping surface.
 *
 * One row per (taxonomy node, business metric) pairing. All writes set
 * costcenterid + customerid from the linked taxonomy node so the impact
 * surface is tenant-scoped and consistent with the node it describes.
 *
 * @package local_sentientia_skillsai
 */
class impact_manager {

    public const IMPACT_TABLE   = 'local_sentientia_skai_impact';
    public const TAXONOMY_TABLE = 'local_sentientia_skai_taxonomy';

    /** Minimum / maximum business priority weight. */
    public const MIN_WEIGHT = 1;
    public const MAX_WEIGHT = 5;

    /**
     * Create a skill -> business-impact mapping.
     *
     * @param int    $taxonomyid   FK to a taxonomy node
     * @param string $metricname   Business metric name
     * @param string $metricdetail Narrative
     * @param int    $weight       1..5 priority
     * @param int    $createdby
     * @return int Impact row id
     * @throws \moodle_exception when the taxonomy node does not exist
     */
    public static function create(int $taxonomyid, string $metricname, string $metricdetail, int $weight, int $createdby): int {
        global $DB;

        $node = $DB->get_record(self::TAXONOMY_TABLE, ['id' => $taxonomyid], '*', MUST_EXIST);
        $now = time();

        $row = new \stdClass();
        $row->customerid    = (int)$node->customerid;
        $row->costcenterid  = (int)$node->costcenterid;
        $row->taxonomyid    = $taxonomyid;
        $row->metric_name   = trim($metricname);
        $row->metric_detail = $metricdetail !== '' ? $metricdetail : null;
        $row->weight        = max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, $weight));
        $row->createdby     = $createdby;
        $row->timecreated   = $now;
        $row->timemodified  = $now;

        return $DB->insert_record(self::IMPACT_TABLE, $row);
    }

    /**
     * Update an existing mapping.
     *
     * @param int   $impactid
     * @param array $updates ['metric_name'=>, 'metric_detail'=>, 'weight'=>]
     * @return void
     */
    public static function update(int $impactid, array $updates): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $impactid;
        $update->timemodified = $now;

        foreach (['metric_name', 'metric_detail'] as $field) {
            if (array_key_exists($field, $updates)) {
                $update->{$field} = $updates[$field];
            }
        }
        if (array_key_exists('weight', $updates)) {
            $update->weight = max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, (int)$updates['weight']));
        }
        $DB->update_record(self::IMPACT_TABLE, $update);
    }

    /**
     * Delete a mapping.
     *
     * @param int $impactid
     * @return void
     */
    public static function delete(int $impactid): void {
        global $DB;
        $DB->delete_records(self::IMPACT_TABLE, ['id' => $impactid]);
    }

    /**
     * List all impact mappings for a tenant, joined to the skill name.
     *
     * @param int $costcenterid
     * @return \stdClass[]
     */
    public static function list_for_tenant(int $costcenterid): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT im.*, t.name AS skillname, t.category AS category
               FROM {" . self::IMPACT_TABLE . "} im
               JOIN {" . self::TAXONOMY_TABLE . "} t ON t.id = im.taxonomyid
              WHERE im.costcenterid = :cid
           ORDER BY im.weight DESC, t.name ASC",
            ['cid' => $costcenterid]
        ));
    }

    /**
     * List mappings for a single taxonomy node.
     *
     * @param int $taxonomyid
     * @return \stdClass[]
     */
    public static function list_for_node(int $taxonomyid): array {
        global $DB;
        return array_values($DB->get_records(self::IMPACT_TABLE,
            ['taxonomyid' => $taxonomyid], 'weight DESC'));
    }
}

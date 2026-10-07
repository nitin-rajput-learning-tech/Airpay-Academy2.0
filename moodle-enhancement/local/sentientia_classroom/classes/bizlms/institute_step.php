<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_location_institutes -> local_sentientia_locations, the institute-level row (MAP).
 *
 * Institutes and rooms land in one table, so the ids cannot be kept. The row carries the tenant as a
 * root organisation id (costcenterid), validated through the tenant resolver; a value that is not a
 * registered root becomes 0 and is reported.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class institute_step extends step {

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.institutes';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_location_institutes';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_locations';
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $warnings = [];

            $name = mapping::text($row, 'fullname');
            if ($name === '') {
                $name = 'Institute ' . $id;
                $warnings[] = 'name_defaulted';
            }

            // The tenant: an institute is always filed under a top-level organisation.
            $costcenterid = 0;
            $costcenter = mapping::int($row, 'costcenter');
            $method = 'unresolved';
            if ($costcenter > 0) {
                [, $root, $method] = $ctx->tenant->resolve(['costcenter' => $costcenter]);
                $costcenterid = (int) $root;
            }
            if ($costcenterid === 0) {
                $warnings[] = 'costcenter_unresolved';
            }

            $type = mapping::int($row, 'institute_type', 0);
            $created = mapping::time_created($row);

            $fields = (object) [
                'name' => $ctx->text->fit($name, 200, 'name'),
                'city' => '',
                'address' => mapping::text($row, 'address') !== '' ? mapping::text($row, 'address') : null,
                'capacity' => 0,
                'equipment' => null,
                'latitude' => null,
                'longitude' => null,
                'costcenterid' => $costcenterid,
                'active' => mapping::int($row, 'visible', 1) === 0 ? 0 : 1,
                'parentid' => null,
                'venue_type' => ($type === 1 || $type === 2) ? $type : null,
                'building' => null,
                'timecreated' => $created,
                'timemodified' => mapping::time_modified($row, $created),
            ];
            $o = outcome::insert($id, 'local_sentientia_locations', $fields)->tenant_method($method);
            foreach ($warnings as $warning) {
                $o->warn($warning);
            }
            $out[] = $o;
        }
        return $out;
    }
}

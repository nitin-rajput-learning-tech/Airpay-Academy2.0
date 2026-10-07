<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_location_room -> local_sentientia_locations, a child row under its institute (MAP).
 *
 * The room's name is "Institute - Room" so that it is a place on its own in the session picker. A room
 * whose institute is missing still imports, with no parent and no tenant.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class room_step extends step {

    /** @var legacy_venues */
    private legacy_venues $venues;

    /**
     * @param legacy_venues $venues Institutes and rooms, shared with the other steps of the importer.
     */
    public function __construct(legacy_venues $venues) {
        $this->venues = $venues;
    }

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.rooms';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_location_room';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_locations';
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_location_institutes', '']];
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

            $institute = $this->venues->institute(mapping::int($row, 'instituteid'), $ctx);
            $parentid = $institute ? $ctx->map->resolve('local_location_institutes', (int) $institute->id) : null;
            if ($institute === null || $parentid === null) {
                $warnings[] = 'institute_not_found';
                $parentid = null;
            }

            [$name, $cut] = legacy_venues::room_name($row, $institute);
            if ($name === '') {
                $name = 'Room ' . $id;
                $warnings[] = 'name_defaulted';
            }
            if ($cut) {
                $warnings[] = 'truncated:name';
            }

            // The room's own address, else its institute's.
            $address = mapping::real_text($row, 'address');
            if ($address === '' && $institute) {
                $address = mapping::text($institute, 'address');
            }
            $building = mapping::real_text($row, 'building');

            $instituteactive = $institute ? mapping::int($institute, 'visible', 1) !== 0 : true;
            $roomactive = mapping::int($row, 'visible', 1) !== 0;

            // The tenant and the venue type come from the institute.
            $costcenterid = 0;
            $venuetype = null;
            if ($institute) {
                $costcenter = mapping::int($institute, 'costcenter');
                if ($costcenter > 0) {
                    [, $root] = $ctx->tenant->resolve(['costcenter' => $costcenter]);
                    $costcenterid = (int) $root;
                }
                $type = mapping::int($institute, 'institute_type', 0);
                $venuetype = ($type === 1 || $type === 2) ? $type : null;
            }

            $created = mapping::time_created($row);
            $fields = (object) [
                'name' => $name,
                'city' => '',
                'address' => $address !== '' ? $address : null,
                'capacity' => max(0, min(mapping::int($row, 'capacity'), mapping::INT_MAX)),
                'equipment' => null,
                'latitude' => null,
                'longitude' => null,
                'costcenterid' => $costcenterid,
                'active' => ($roomactive && $instituteactive) ? 1 : 0,
                'parentid' => $parentid,
                'venue_type' => $venuetype,
                'building' => $building !== '' ? $ctx->text->fit($building, 225, 'building') : null,
                'timecreated' => $created,
                'timemodified' => mapping::time_modified($row, $created),
            ];
            $o = outcome::insert($id, 'local_sentientia_locations', $fields);
            foreach ($warnings as $warning) {
                $o->warn($warning);
            }
            $out[] = $o;
        }
        return $out;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;

defined('MOODLE_INTERNAL') || die();

/**
 * The BizLMS institutes and rooms, read once per import run through the context's bounded reader.
 *
 * Three steps need the text of a venue (the room step builds "Institute - Room", the classroom step
 * and the session step copy the institute or room name into their own location column). The two
 * tables hold a few dozen to a few hundred rows, so they are read in full, in keyset pages, the first
 * time a step asks, instead of one SELECT per classroom or session.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_venues {

    /** Rows per page. */
    private const PAGE = 5000;

    /** Columns read from local_location_institutes. */
    private const INSTITUTE_COLUMNS = ['id', 'costcenter', 'fullname', 'address', 'visible', 'institute_type',
        'timecreated', 'timemodified'];

    /** Columns read from local_location_room. */
    private const ROOM_COLUMNS = ['id', 'instituteid', 'name', 'building', 'address', 'capacity', 'visible',
        'timecreated', 'timemodified'];

    /** @var array<int, \stdClass>|null Institute id => row. */
    private ?array $institutes = null;

    /** @var array<int, \stdClass>|null Room id => row. */
    private ?array $rooms = null;

    /**
     * @param int $id
     * @param context $ctx
     * @return \stdClass|null The institute row, or null when there is none.
     */
    public function institute(int $id, context $ctx): ?\stdClass {
        if ($this->institutes === null) {
            $this->institutes = $this->load($ctx, 'local_location_institutes', self::INSTITUTE_COLUMNS);
        }
        return $this->institutes[$id] ?? null;
    }

    /**
     * @param int $id
     * @param context $ctx
     * @return \stdClass|null The room row, or null when there is none.
     */
    public function room(int $id, context $ctx): ?\stdClass {
        if ($this->rooms === null) {
            $this->rooms = $this->load($ctx, 'local_location_room', self::ROOM_COLUMNS);
        }
        return $this->rooms[$id] ?? null;
    }

    /**
     * The name of a room as a place, and whether the institute part had to be cut.
     *
     * @param \stdClass $room
     * @param \stdClass|null $institute
     * @return array{0: string, 1: bool}
     */
    public static function room_name(\stdClass $room, ?\stdClass $institute): array {
        return mapping::room_location_name(
            $institute ? mapping::text($institute, 'fullname') : '',
            mapping::text($room, 'name'));
    }

    /**
     * Read a whole legacy table in keyset pages.
     *
     * @param context $ctx
     * @param string $table
     * @param string[] $columns
     * @return array<int, \stdClass> id => row; empty when the table is missing
     */
    private function load(context $ctx, string $table, array $columns): array {
        if (!$ctx->legacy->exists($table)) {
            return [];
        }
        $rows = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page($table, $after, self::PAGE, $columns);
            foreach ($page as $id => $row) {
                $rows[(int) $id] = $row;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $rows;
    }
}

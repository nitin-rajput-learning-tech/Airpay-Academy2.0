<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_classroom\url_rule;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom_sessions -> local_sentientia_classroom_sessions (PRESERVE).
 *
 * Session ids are kept because the calendar events BizLMS wrote (core {event}.plugin_itemid) and the
 * completion rules it stored (a CSV of session ids) hold them, and the import rewrites neither.
 * The session's trainer is carried because the attendance pages (grid and QR) let a trainer in only for a
 * session whose trainerid, or whose classroom's trainerid, is theirs: a session imported without its
 * trainer would lock the trainer out.
 *
 * Not copied: onlinesession, datetimeknown, duration (used once, to rebuild a missing end), sessiontimezone,
 * attendance_status, moduletype, moduleid, usercreated, usermodified, capacity.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_step extends step {

    /** @var legacy_venues */
    private legacy_venues $venues;

    /**
     * @param legacy_venues $venues
     */
    public function __construct(legacy_venues $venues) {
        $this->venues = $venues;
    }

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.sessions';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom_sessions';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_sessions';
    }

    /**
     * @return string
     */
    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Rows the import does not rewrite that hold a session id.
     *
     * @return array<array{0: string, 1: string, 2: string}>
     */
    public function external_refs(): array {
        return [
            ['event', 'plugin_itemid', "plugin = 'local_classroom' AND local_eventtype = 'session_open'"],
            ['local_classroom_completion', 'sessionids', 'comma separated list of session ids'],
        ];
    }

    /**
     * A header copy is recognised by the session's name and creation time. The target calls the name title.
     *
     * @return array<int|string, string>
     */
    public function adopt_signature(): array {
        return ['name' => 'title', 'timecreated'];
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_classroom', ''], ['local_location_institutes', ''], ['local_location_room', '']];
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

            $classroomid = $ctx->map->resolve('local_classroom', mapping::int($row, 'classroomid'));
            if ($classroomid === null) {
                $out[] = outcome::skip($id, 'orphan_classroom');
                continue;
            }

            $start = mapping::time($row, 'timestart');
            [$end, $endwarning] = mapping::session_end($start, mapping::time($row, 'timefinish'),
                mapping::int($row, 'duration'));
            if ($endwarning !== '') {
                $warnings[] = $endwarning;
            }

            // The place: the room (as "Institute - Room"), else the institute, else nothing; the
            // classroom's own location is what a reader falls back to.
            $locationid = null;
            $location = null;
            $roomid = mapping::int($row, 'roomid');
            $instituteid = mapping::int($row, 'instituteid');
            $room = $roomid > 0 ? $this->venues->room($roomid, $ctx) : null;
            if ($room !== null) {
                $institute = $this->venues->institute(mapping::int($room, 'instituteid'), $ctx);
                $locationid = $ctx->map->resolve('local_location_room', $roomid);
                [$name] = legacy_venues::room_name($room, $institute);
                $location = $name !== '' ? $name : null;
            } else {
                if ($roomid > 0) {
                    $warnings[] = 'room_not_found';
                }
                $institute = $instituteid > 0 ? $this->venues->institute($instituteid, $ctx) : null;
                if ($institute !== null) {
                    $locationid = $ctx->map->resolve('local_location_institutes', $instituteid);
                    $text = $ctx->text->fit(mapping::text($institute, 'fullname'), 254, 'location');
                    $location = $text !== '' ? $text : null;
                } else if ($instituteid > 0) {
                    $warnings[] = 'institute_not_found';
                }
            }

            // The session's own trainer; the user must still exist.
            $trainerid = mapping::actor($row, 'trainerid');
            if ($trainerid !== null && !$ctx->lookups->user_exists($trainerid)) {
                $warnings[] = 'trainer_not_found';
                $trainerid = null;
            }

            // The links go through the rule a typed link goes through.
            $meeting = url_rule::sanitize_legacy(mapping::text($row, 'messagelink'));
            $recording = url_rule::sanitize_legacy(mapping::text($row, 'recordinglink'));
            if ($meeting === null && mapping::text($row, 'messagelink') !== '') {
                $warnings[] = 'url_sanitised';
            }
            if ($recording === null && mapping::text($row, 'recordinglink') !== '') {
                $warnings[] = 'url_sanitised';
            }

            $created = mapping::time_created($row);
            $description = isset($row->description) ? (string) $row->description : '';
            $o = outcome::insert($id, 'local_sentientia_classroom_sessions', (object) [
                'classroomid' => $classroomid,
                'title' => $ctx->text->fit(mapping::text($row, 'name'), 254, 'title'),
                'sessiondate' => $start,
                'starttime' => $start,
                'endtime' => $end,
                'location' => $location,
                'locationid' => $locationid,
                'trainerid' => $trainerid,
                'notes' => trim($description) !== '' ? $description : null,
                'meeting_url' => $meeting,
                'recording_url' => $recording,
                'timecreated' => $created,
                'timemodified' => mapping::time_modified($row, $created),
            ]);
            foreach ($warnings as $warning) {
                $o->warn($warning);
            }
            $out[] = $o;
        }
        return $out;
    }
}

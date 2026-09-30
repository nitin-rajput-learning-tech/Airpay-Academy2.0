<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The value rules of the BizLMS classroom import, as pure functions (ADR-032, mapping doc section 15).
 *
 * Nothing here reads or writes the database, so the steps stay pure and every rule has a test that
 * needs no legacy tables.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mapping {

    /** BizLMS classroom status: not started yet. */
    public const BZ_NEW = 0;
    /** BizLMS classroom status: running. */
    public const BZ_ACTIVE = 1;
    /** BizLMS classroom status: on hold. */
    public const BZ_HOLD = 2;
    /** BizLMS classroom status: cancelled. */
    public const BZ_CANCELLED = 3;
    /** BizLMS classroom status: completed. */
    public const BZ_COMPLETED = 4;

    /** Sentientia classroom status: cancelled. */
    public const CANCELLED = 0;
    /** Sentientia classroom status: active. */
    public const ACTIVE = 1;
    /** Sentientia classroom status: completed. */
    public const COMPLETED = 2;
    /** Sentientia classroom status: draft (new with ADR-032). */
    public const DRAFT = 5;
    /** Sentientia classroom status: on hold (new with ADR-032). */
    public const ON_HOLD = 6;

    /** Largest value the MEDIUMINT capacity column holds (install.xml: INT(6)). */
    public const CAPACITY_MAX = 8388607;

    /** Largest value the INT(10) columns the import fills hold without a warning. */
    public const INT_MAX = 2147483647;

    /**
     * The Sentientia status of a BizLMS classroom status.
     *
     * 1 -> 1, 3 -> 0, 4 -> 2. The two states Sentientia did not have become 5 (draft, BizLMS 0) and 6 (on hold,
     * BizLMS 2) when $newstates is true; 3 and 4 are never reused for them because they are raw BizLMS values an
     * earlier copy may have left in the table. With $newstates false (decision collapse_active) both become 1.
     *
     * @param int $legacy
     * @param bool $newstates decision classroom.status_new_hold is add_5_6
     * @return int|null Null for a value BizLMS never used (preflight blocks those, so this is a guard).
     */
    public static function classroom_status(int $legacy, bool $newstates): ?int {
        switch ($legacy) {
            case self::BZ_NEW:
                return $newstates ? self::DRAFT : self::ACTIVE;
            case self::BZ_ACTIVE:
                return self::ACTIVE;
            case self::BZ_HOLD:
                return $newstates ? self::ON_HOLD : self::ACTIVE;
            case self::BZ_CANCELLED:
                return self::CANCELLED;
            case self::BZ_COMPLETED:
                return self::COMPLETED;
        }
        return null;
    }

    /**
     * Has the classroom ended (cancelled or completed in BizLMS)?
     *
     * @param int $legacy BizLMS status
     * @return bool
     */
    public static function is_closed(int $legacy): bool {
        return $legacy === self::BZ_CANCELLED || $legacy === self::BZ_COMPLETED;
    }

    /**
     * The Sentientia attendance status of a BizLMS one.
     *
     * 1 (present) -> 1, 2 (absent) -> 0. 0 is the placeholder BizLMS wrote for a learner nobody marked:
     * a missing row already reads as Absent in Sentientia, so it is archived, and so is an empty value.
     *
     * @param int|null $legacy
     * @return int|null Null for the placeholder.
     */
    public static function attendance_status(?int $legacy): ?int {
        if ($legacy === 1) {
            return 1;
        }
        if ($legacy === 2) {
            return 0;
        }
        return null;
    }

    /**
     * An integer column of a source row; NULL, a missing column and an empty string read as $default.
     *
     * @param \stdClass $row
     * @param string $column
     * @param int $default
     * @return int
     */
    public static function int(\stdClass $row, string $column, int $default = 0): int {
        if (!isset($row->{$column}) || $row->{$column} === '') {
            return $default;
        }
        return (int) $row->{$column};
    }

    /**
     * Is the column NULL, empty or missing?
     *
     * @param \stdClass $row
     * @param string $column
     * @return bool
     */
    public static function missing(\stdClass $row, string $column): bool {
        return !isset($row->{$column}) || $row->{$column} === '';
    }

    /**
     * A text column of a source row, trimmed; NULL and a missing column read as the empty string.
     *
     * @param \stdClass $row
     * @param string $column
     * @return string
     */
    public static function text(\stdClass $row, string $column): string {
        return isset($row->{$column}) ? trim((string) $row->{$column}) : '';
    }

    /**
     * A text column whose BizLMS default is the four letters NULL (the install file writes DEFAULT="NULL"
     * for building, address, sessiontimezone and moduletype), so the letters are "no value".
     *
     * @param \stdClass $row
     * @param string $column
     * @return string
     */
    public static function real_text(\stdClass $row, string $column): string {
        $value = self::text($row, $column);
        return strcasecmp($value, 'NULL') === 0 ? '' : $value;
    }

    /**
     * A non-negative timestamp column; a missing, empty or negative value is 0.
     *
     * @param \stdClass $row
     * @param string $column
     * @return int
     */
    public static function time(\stdClass $row, string $column): int {
        return max(0, self::int($row, $column));
    }

    /**
     * timecreated of a source row, kept as the source has it.
     *
     * @param \stdClass $row
     * @return int
     */
    public static function time_created(\stdClass $row): int {
        return self::time($row, 'timecreated');
    }

    /**
     * timemodified of a source row; 0, NULL and the source default of 1 mean "never modified" and read as
     * timecreated (the locations install file defaults it to 1).
     *
     * @param \stdClass $row
     * @param int $created the row's timecreated
     * @return int
     */
    public static function time_modified(\stdClass $row, int $created): int {
        $modified = self::time($row, 'timemodified');
        return $modified <= 1 ? $created : $modified;
    }

    /**
     * 0 and NULL dates become NULL, so "no date" is distinguishable from the epoch.
     *
     * @param \stdClass $row
     * @param string $column
     * @return int|null
     */
    public static function date_or_null(\stdClass $row, string $column): ?int {
        $value = self::time($row, $column);
        return $value > 0 ? $value : null;
    }

    /**
     * A user id column as an actor: a positive id, else NULL.
     *
     * @param \stdClass $row
     * @param string $column
     * @return int|null
     */
    public static function actor(\stdClass $row, string $column): ?int {
        $value = self::int($row, $column);
        return $value > 0 ? $value : null;
    }

    /**
     * Keep a value in 0..$max. A value above the maximum is cut to it, not refused: the writer would
     * refuse the whole batch.
     *
     * @param int $value
     * @param int $max
     * @return int
     */
    public static function clamp(int $value, int $max): int {
        return max(0, min($value, $max));
    }

    /**
     * The path segments of a normalised tenant path ('/1/5' gives [1, 5]).
     *
     * @param string|null $path
     * @return int[]
     */
    public static function path_segments(?string $path): array {
        $segments = [];
        foreach (explode('/', trim((string) $path)) as $segment) {
            if ($segment !== '' && ctype_digit($segment)) {
                $segments[] = (int) $segment;
            }
        }
        return $segments;
    }

    /**
     * The organisation id a classroom is filed under: the last segment of its path. The edit form
     * preselects it.
     *
     * @param string|null $path
     * @return int 0 when there is no path
     */
    public static function costcenter_of_path(?string $path): int {
        $segments = self::path_segments($path);
        return $segments ? (int) end($segments) : 0;
    }

    /**
     * The department a classroom is filed under: the second segment of its path, or 0.
     *
     * @param string|null $path
     * @return int
     */
    public static function department_of_path(?string $path): int {
        $segments = self::path_segments($path);
        return $segments[1] ?? 0;
    }

    /**
     * End of a session.
     *
     * BizLMS stored a finish time and, separately, a duration in minutes. A finish that is not after the
     * start, together with a positive duration, is rebuilt as start + duration (attendance.php:123 counts
     * minutes). Otherwise the stored finish is kept even when it is not after the start: update_session
     * refuses such a session, but the import must not invent a time, so it reports it.
     *
     * @param int $start
     * @param int $finish
     * @param int $durationminutes
     * @return array{0: int, 1: string} [end, warning code or '']
     */
    public static function session_end(int $start, int $finish, int $durationminutes): array {
        if ($finish > $start) {
            return [$finish, ''];
        }
        // A session with no start (BizLMS datetimeknown = 0) has nothing to add a duration to.
        if ($start > 0 && $durationminutes > 0) {
            return [$start + $durationminutes * 60, 'derived_timestamp'];
        }
        return [$finish, 'end_not_after_start'];
    }

    /**
     * The name of a room as a place: "Institute - Room", the institute part cut so the room name stays whole
     * within $max characters (locations.name is 200, sessions.location 254).
     *
     * @param string $institute
     * @param string $room
     * @param int $max
     * @return array{0: string, 1: bool} [name, the institute part was cut]
     */
    public static function room_location_name(string $institute, string $room, int $max = 200): array {
        $institute = trim($institute);
        $room = trim($room);
        if ($institute === '') {
            return [\core_text::substr($room, 0, $max), \core_text::strlen($room) > $max];
        }
        if ($room === '') {
            return [\core_text::substr($institute, 0, $max), \core_text::strlen($institute) > $max];
        }
        $separator = ' - ';
        $budget = $max - \core_text::strlen($separator) - \core_text::strlen($room);
        if ($budget < 1) {
            // The room name alone fills the column.
            return [\core_text::substr($room, 0, $max), true];
        }
        $cut = \core_text::strlen($institute) > $budget;
        return [\core_text::substr($institute, 0, $budget) . $separator . $room, $cut];
    }

    /**
     * Dense positions for the learners who are still waiting: 1..N by (BizLMS sort order, id).
     *
     * @param array<int, int> $sortorders source id => BizLMS sortorder, only the rows that stay waiting
     * @return array<int, int> source id => position
     */
    public static function dense_positions(array $sortorders): array {
        $ids = array_keys($sortorders);
        usort($ids, static function (int $a, int $b) use ($sortorders): int {
            return [$sortorders[$a], $a] <=> [$sortorders[$b], $b];
        });
        $positions = [];
        $position = 0;
        foreach ($ids as $id) {
            $positions[$id] = ++$position;
        }
        return $positions;
    }

    /**
     * The waiting-list status of a BizLMS waiting-list row.
     *
     * BizLMS enrolstatus 1 means the learner was moved onto the roster: promoted. 0 means waiting, and then:
     * a learner who is on the roster anyway is promoted ("already enrolled"); on a classroom that has ended
     * (cancelled or completed) the place is removed, because nobody can be promoted into it, unless the
     * owner decided otherwise; on any other classroom the place stays waiting when the owner's decision lets it.
     *
     * @param int $enrolstatus BizLMS enrolstatus (NULL read as 0)
     * @param bool $onroster the learner is on the classroom's roster
     * @param int $classroomstatus BizLMS classroom status
     * @param bool $closedstaywaiting decision classroom.waitlist_closed is waiting
     * @param bool $openstaywaiting decision classroom.waitlist_open lets open classrooms keep waiting places
     * @return array{0: string, 1: string} [status, note]: waiting, promoted or removed, and the note
     *         ('already_enrolled', 'classroom_closed', 'kept_out_of_the_queue' or '')
     */
    public static function waitlist_status(int $enrolstatus, bool $onroster, int $classroomstatus,
            bool $closedstaywaiting, bool $openstaywaiting): array {
        if ($enrolstatus === 1) {
            return ['promoted', ''];
        }
        if ($onroster) {
            return ['promoted', 'already_enrolled'];
        }
        if (self::is_closed($classroomstatus)) {
            return $closedstaywaiting ? ['waiting', ''] : ['removed', 'classroom_closed'];
        }
        return $openstaywaiting ? ['waiting', ''] : ['removed', 'kept_out_of_the_queue'];
    }

    /**
     * The text stored in waitlist.reason for an imported row.
     *
     * @param string $note a note from waitlist_status()
     * @return string|null
     */
    public static function waitlist_reason(string $note): ?string {
        switch ($note) {
            case 'already_enrolled':
                return 'Imported from BizLMS: already enrolled';
            case 'classroom_closed':
                return 'Imported from BizLMS: classroom closed before promotion';
            case 'kept_out_of_the_queue':
                return 'Imported from BizLMS: not kept in the queue';
        }
        return null;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for sentientia_classroom.
// Covers the roster, attendance and (since 2026-09-24) the waiting list:
// discovery, export, core's full erasure and the Sentientia DPDP
// anonymise_data_for_user() hook.

namespace local_sentientia_classroom\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * The waiting list. Created by upgrade step 2026051130, and only declared
     * in db/install.xml from 2026-09-24, so a site installed fresh before
     * then has no such table until upgrade step 2026092400 adds it. Every
     * access below is behind waitlist_exists(): erasure must never throw on
     * a site without it.
     */
    private const WAITLIST = 'local_sentientia_classroom_waitlist';

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_classroom_users',
            [
                'classroomid' => 'privacy:metadata:roster:classroomid',
                'userid'      => 'privacy:metadata:roster:userid',
                'timecreated' => 'privacy:metadata:roster:timecreated',
            ],
            'privacy:metadata:roster');
        $collection->add_database_table('local_sentientia_classroom_attendance',
            [
                'sessionid'  => 'privacy:metadata:attendance:sessionid',
                'userid'     => 'privacy:metadata:attendance:userid',
                'status'     => 'privacy:metadata:attendance:status',
                'markedat'   => 'privacy:metadata:attendance:markedat',
                'markedby'   => 'privacy:metadata:attendance:markedby',
            ],
            'privacy:metadata:attendance');
        $collection->add_database_table(self::WAITLIST,
            [
                'classroomid' => 'privacy:metadata:waitlist:classroomid',
                'userid'      => 'privacy:metadata:waitlist:userid',
                'position'    => 'privacy:metadata:waitlist:position',
                'status'      => 'privacy:metadata:waitlist:status',
                'reason'      => 'privacy:metadata:waitlist:reason',
                'promoted_at' => 'privacy:metadata:waitlist:promoted_at',
                'removed_at'  => 'privacy:metadata:waitlist:removed_at',
                'timecreated' => 'privacy:metadata:waitlist:timecreated',
            ],
            'privacy:metadata:waitlist');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_sentientia_classroom_users', ['userid' => $userid])
            || $DB->record_exists('local_sentientia_classroom_attendance', ['userid' => $userid])
            || (static::waitlist_exists() && $DB->record_exists(self::WAITLIST, ['userid' => $userid]))) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $userid = $contextlist->get_user()->id;
        $roster = $DB->get_records('local_sentientia_classroom_users',
            ['userid' => $userid]);
        $attendance = $DB->get_records('local_sentientia_classroom_attendance',
            ['userid' => $userid]);
        $waitlist = static::waitlist_exists()
            ? $DB->get_records(self::WAITLIST, ['userid' => $userid], 'id ASC')
            : [];
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_classroom'],
                (object) [
                    'roster_count'     => count($roster),
                    'roster'           => array_values((array) $roster),
                    'attendance_count' => count($attendance),
                    'attendance'       => array_values((array) $attendance),
                    'waitlist_count'   => count($waitlist),
                    'waitlist'         => array_values((array) $waitlist),
                ]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_sentientia_classroom_users');
        $DB->delete_records('local_sentientia_classroom_attendance');
        if (static::waitlist_exists()) {
            $DB->delete_records(self::WAITLIST);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_classroom_users', ['userid' => $uid]);
        $DB->delete_records('local_sentientia_classroom_attendance', ['userid' => $uid]);
        self::delete_waitlist_rows([(int) $uid]);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = (int) $contextlist->get_user()->id;
        // Attendance is the only record that the employee attended an ILT or
        // compliance session - the classroom's course completion. Keep it,
        // keyed to the anonymised row (rewriting userid to 0 would also break
        // UNIQUE(sessionid, userid)); clear only the free-text note. The
        // roster row carries no completion and goes.
        $DB->delete_records('local_sentientia_classroom_users', ['userid' => $uid]);
        $DB->set_field('local_sentientia_classroom_attendance', 'notes', null, ['userid' => $uid]);
        // A waiting-list place is a queue entry, not a learning record, and
        // `reason` can hold an admin's free text about this person. It goes,
        // exactly as in the full erasure.
        self::delete_waitlist_rows([$uid]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $u1 = $DB->get_fieldset_select('local_sentientia_classroom_users',
            'DISTINCT userid', 'userid > 0');
        $u2 = $DB->get_fieldset_select('local_sentientia_classroom_attendance',
            'DISTINCT userid', 'userid > 0');
        $u3 = static::waitlist_exists()
            ? $DB->get_fieldset_select(self::WAITLIST, 'DISTINCT userid', 'userid > 0')
            : [];
        $userids = array_unique(array_merge((array) $u1, (array) $u2, (array) $u3));
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_classroom_users',
            "userid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_classroom_attendance',
            "userid $insql", $inparams);
        self::delete_waitlist_rows(array_map('intval', $userids));
    }

    /**
     * Whether the waiting-list table is present on this site (see WAITLIST).
     * Resolved with static:: so a test double can report it absent.
     */
    protected static function waitlist_exists(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::WAITLIST);
    }

    /**
     * Delete these users' waiting-list rows, every status, then renumber each
     * queue they were still waiting in: list_waitlist shows the position, and
     * a deleted head would otherwise leave everyone behind it one place too
     * far back. Renumbering touches only other people's position and
     * timemodified. No-op when the table is absent.
     *
     * @param int[] $userids
     */
    private static function delete_waitlist_rows(array $userids): void {
        global $DB;
        if (empty($userids) || !static::waitlist_exists()) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'wluid');
        $classroomids = $DB->get_fieldset_select(self::WAITLIST, 'DISTINCT classroomid',
            "userid $insql AND status = :waiting", $inparams + ['waiting' => 'waiting']);
        $DB->delete_records_select(self::WAITLIST, "userid $insql", $inparams);
        foreach ($classroomids as $classroomid) {
            \local_sentientia_classroom\waitlist_manager::renumber_positions((int) $classroomid);
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}

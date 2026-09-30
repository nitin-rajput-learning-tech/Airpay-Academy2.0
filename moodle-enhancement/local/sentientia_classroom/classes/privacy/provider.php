<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for sentientia_classroom.
// Covers the roster, attendance and (since 2026-09-24) the waiting list:
// discovery, export, core's full erasure and the Sentientia DPDP
// anonymise_data_for_user() hook. Since 2026-09-30 (ADR-032, BizLMS classroom
// import) it also covers the people named as trainers and creators, and the
// completion the roster carries.

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

    /** The classrooms: trainerid and createdby name people. */
    private const CLASSROOMS = 'local_sentientia_classroom';

    /** The sessions: trainerid names a person. */
    private const SESSIONS = 'local_sentientia_classroom_sessions';

    /** Every trainer of a classroom (ADR-032); trainerid names a person. */
    private const TRAINERS = 'local_sentientia_classroom_trainers';

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_classroom_users',
            [
                'classroomid'       => 'privacy:metadata:roster:classroomid',
                'userid'            => 'privacy:metadata:roster:userid',
                'enrolledby'        => 'privacy:metadata:roster:enrolledby',
                'completion_status' => 'privacy:metadata:roster:completion_status',
                'timecompleted'     => 'privacy:metadata:roster:timecompleted',
                'hours'             => 'privacy:metadata:roster:hours',
                'timecreated'       => 'privacy:metadata:roster:timecreated',
            ],
            'privacy:metadata:roster');
        $collection->add_database_table('local_sentientia_classroom_attendance',
            [
                'sessionid'  => 'privacy:metadata:attendance:sessionid',
                'userid'     => 'privacy:metadata:attendance:userid',
                'status'     => 'privacy:metadata:attendance:status',
                'markedat'   => 'privacy:metadata:attendance:markedat',
                'markedby'   => 'privacy:metadata:attendance:markedby',
                'notes'      => 'privacy:metadata:attendance:notes',
            ],
            'privacy:metadata:attendance');
        $collection->add_database_table(self::CLASSROOMS,
            [
                'trainerid' => 'privacy:metadata:classroom:trainerid',
                'createdby' => 'privacy:metadata:classroom:createdby',
            ],
            'privacy:metadata:classroom');
        $collection->add_database_table(self::SESSIONS,
            [
                'trainerid' => 'privacy:metadata:sessions:trainerid',
            ],
            'privacy:metadata:sessions');
        $collection->add_database_table(self::TRAINERS,
            [
                'classroomid' => 'privacy:metadata:trainers:classroomid',
                'trainerid'   => 'privacy:metadata:trainers:trainerid',
                'timecreated' => 'privacy:metadata:trainers:timecreated',
            ],
            'privacy:metadata:trainers');
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
            || (static::waitlist_exists() && $DB->record_exists(self::WAITLIST, ['userid' => $userid]))
            || self::is_named_as_trainer_or_creator($userid)) {
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
        // The classrooms and sessions the user trains, and the classrooms they created (ids and names).
        $trainerrows = self::table_present(self::TRAINERS)
            ? $DB->get_records(self::TRAINERS, ['trainerid' => $userid], 'id ASC')
            : [];
        $classrooms = $DB->get_records_select(self::CLASSROOMS, 'trainerid = :t OR createdby = :c',
            ['t' => $userid, 'c' => $userid], 'id ASC', 'id, name, trainerid, createdby');
        $sessionsled = $DB->get_records(self::SESSIONS, ['trainerid' => $userid], 'id ASC', 'id, classroomid, title');
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
                    'trainer_count'    => count($trainerrows),
                    'trainer'          => array_values((array) $trainerrows),
                    'classrooms'       => array_values((array) $classrooms),
                    'sessions_led'     => array_values((array) $sessionsled),
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
        if (self::table_present(self::TRAINERS)) {
            $DB->delete_records(self::TRAINERS);
        }
        $DB->set_field_select(self::CLASSROOMS, 'trainerid', null, 'trainerid IS NOT NULL');
        $DB->set_field_select(self::CLASSROOMS, 'createdby', null, 'createdby IS NOT NULL');
        $DB->set_field_select(self::SESSIONS, 'trainerid', null, 'trainerid IS NOT NULL');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_classroom_users', ['userid' => $uid]);
        $DB->delete_records('local_sentientia_classroom_attendance', ['userid' => $uid]);
        self::delete_waitlist_rows([(int) $uid]);
        self::release_trainer_and_creator([(int) $uid]);
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
        // Attendance is a record that the employee attended an ILT or compliance
        // session, and since ADR-032 the roster row carries the classroom's
        // completion (completion_status, timecompleted, hours - the BizLMS history
        // the import brought in). Keep both, keyed to the anonymised user row
        // (rewriting userid to 0 would also break the UNIQUE keys); clear only
        // the free-text note. Neither the roster row nor the trainer rows hold
        // free text, so there is nothing to clear on them: as in
        // local_sentientia_programs and local_sentientia_learningpath the
        // records stay and the person is gone.
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
        $u4 = self::table_present(self::TRAINERS)
            ? $DB->get_fieldset_select(self::TRAINERS, 'DISTINCT trainerid', 'trainerid > 0')
            : [];
        $u5 = $DB->get_fieldset_select(self::CLASSROOMS, 'DISTINCT trainerid', 'trainerid > 0');
        $u6 = $DB->get_fieldset_select(self::CLASSROOMS, 'DISTINCT createdby', 'createdby > 0');
        $u7 = $DB->get_fieldset_select(self::SESSIONS, 'DISTINCT trainerid', 'trainerid > 0');
        $userids = array_unique(array_merge((array) $u1, (array) $u2, (array) $u3, (array) $u4,
            (array) $u5, (array) $u6, (array) $u7));
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
        self::release_trainer_and_creator(array_map('intval', $userids));
    }

    /**
     * Is this table there? The two tables ADR-032 added are created by an upgrade step, and erasure must
     * never throw on a site that has not run it.
     *
     * @param string $table
     * @return bool
     */
    private static function table_present(string $table): bool {
        global $DB;
        return $DB->get_manager()->table_exists($table);
    }

    /**
     * Does any classroom, session or trainer row name this user as a trainer or creator?
     *
     * @param int $userid
     * @return bool
     */
    private static function is_named_as_trainer_or_creator(int $userid): bool {
        global $DB;
        return (self::table_present(self::TRAINERS) && $DB->record_exists(self::TRAINERS, ['trainerid' => $userid]))
            || $DB->record_exists(self::CLASSROOMS, ['trainerid' => $userid])
            || $DB->record_exists(self::CLASSROOMS, ['createdby' => $userid])
            || $DB->record_exists(self::SESSIONS, ['trainerid' => $userid]);
    }

    /**
     * Full erasure of users who train or created classrooms: their trainer rows go (a trainer row is only
     * the statement "this person trains this classroom"), and the classroom and session columns that name
     * them are cleared. The classrooms and sessions themselves stay.
     *
     * @param int[] $userids
     * @return void
     */
    private static function release_trainer_and_creator(array $userids): void {
        global $DB;
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rtc');
        if (self::table_present(self::TRAINERS)) {
            $DB->delete_records_select(self::TRAINERS, "trainerid $insql", $inparams);
        }
        $DB->set_field_select(self::CLASSROOMS, 'trainerid', null, "trainerid $insql", $inparams);
        $DB->set_field_select(self::CLASSROOMS, 'createdby', null, "createdby $insql", $inparams);
        $DB->set_field_select(self::SESSIONS, 'trainerid', null, "trainerid $insql", $inparams);
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

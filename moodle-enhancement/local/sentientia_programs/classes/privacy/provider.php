<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_programs_users',
            [
                'programid'      => 'privacy:metadata:enrol:programid',
                'userid'         => 'privacy:metadata:enrol:userid',
                'currentlevelid' => 'privacy:metadata:enrol:currentlevelid',
                'status'         => 'privacy:metadata:enrol:status',
                'timecreated'    => 'privacy:metadata:enrol:timecreated',
                'timecompleted'  => 'privacy:metadata:enrol:timecompleted',
            ],
            'privacy:metadata:enrol');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_sentientia_programs_users', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $rows = $DB->get_records('local_sentientia_programs_users',
            ['userid' => $contextlist->get_user()->id]);
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_programs'],
                (object) ['enrolments' => array_values((array) $rows)]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_sentientia_programs_users');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $DB->delete_records('local_sentientia_programs_users',
            ['userid' => $contextlist->get_user()->id]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $DB->get_fieldset_select('local_sentientia_programs_users',
            'DISTINCT userid', 'userid > 0');
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_programs_users',
            "userid $insql", $inparams);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it, so delete_data_for_user() above stays the
     * full erasure.
     *
     * Table-by-table (2026-09-24):
     *   - local_sentientia_programs_users: KEEP, unchanged. It is the
     *     certification-program enrolment AND completion record (status 2 =
     *     completed, with timecompleted; currentlevelid is how far the person
     *     got). A certification is exactly what an auditor asks to see after
     *     the person has gone, and the flow keeps the core course completions
     *     it was earned from, so deleting it here would leave those orphaned
     *     from the certificate they add up to. Every column is record data
     *     (ids, a status code, timestamps); there is no free text to blank.
     *   - local_sentientia_programs, _levels, _courses: program definitions
     *     with no user column. Nothing to erase or keep.
     *
     * So the body is deliberately empty: the method's existence is what stops
     * the DPDP flow calling delete_data_for_user() and destroying the
     * certification record.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist) {
        if (!self::has_system_context($contextlist)) {
            return;
        }
        // Nothing to erase: see the table-by-table note above. If a
        // free-text or contact column is ever added to programs_users, blank
        // it here rather than deleting the row.
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}

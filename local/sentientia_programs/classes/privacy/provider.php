<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for local_sentientia_programs.
 *
 * Personal data lives in four tables:
 *
 *   local_sentientia_programs_users      the learner's enrolment and completion (userid), and who enrolled them
 *                                        (enrolledby, an actor column)
 *   local_sentientia_programs_lvlcomp    the learner's stored level completions (userid), ADR-032
 *   local_sentientia_programs_trainers   a trainer assigned to a program (userid), and who assigned them
 *                                        (assignedby), ADR-032
 *   local_sentientia_programs_trainerfb  feedback on a trainer (trainerid), and the learner who gave it
 *                                        (userid, may be empty), ADR-032
 *
 * The three ADR-032 tables are filled by the BizLMS import and, for trainers, have no Sentientia writer yet. They
 * are declared and handled all the same: a table that names a person and is missing from the provider is the
 * defect the structural guard (local_sentientia_platform privacy_coverage_test) exists to catch.
 *
 * Two erasure paths, which differ as they did before:
 *   - Core's privacy API (delete_data_for_user and friends): the person's own rows go. A reference to a person in
 *     somebody else's row (who enrolled them, who assigned them, who gave feedback) is cleared and the row stays.
 *   - The Sentientia DPDP flow (anonymise_data_for_user): the certification record is KEPT, still keyed to the user
 *     row that flow anonymises in place; only references to the person in other people's records are cleared.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** Table: enrolments. */
    private const USERS = 'local_sentientia_programs_users';
    /** Table: stored level completions. */
    private const LVLCOMP = 'local_sentientia_programs_lvlcomp';
    /** Table: trainers of a program. */
    private const TRAINERS = 'local_sentientia_programs_trainers';
    /** Table: feedback on a trainer. */
    private const TRAINERFB = 'local_sentientia_programs_trainerfb';

    /**
     * Every column that names a person, by table. A user is "in" a context when any of these holds their id.
     */
    private const PERSON_COLUMNS = [
        self::USERS => ['userid', 'enrolledby'],
        self::LVLCOMP => ['userid'],
        self::TRAINERS => ['userid', 'assignedby'],
        self::TRAINERFB => ['trainerid', 'userid'],
    ];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::USERS,
            [
                'programid'      => 'privacy:metadata:enrol:programid',
                'userid'         => 'privacy:metadata:enrol:userid',
                'currentlevelid' => 'privacy:metadata:enrol:currentlevelid',
                'status'         => 'privacy:metadata:enrol:status',
                'timecreated'    => 'privacy:metadata:enrol:timecreated',
                'timecompleted'  => 'privacy:metadata:enrol:timecompleted',
                'enrolledby'     => 'privacy:metadata:enrol:enrolledby',
                'timemodified'   => 'privacy:metadata:enrol:timemodified',
            ],
            'privacy:metadata:enrol');
        $collection->add_database_table(self::LVLCOMP,
            [
                'programid'          => 'privacy:metadata:lvlcomp:programid',
                'levelid'            => 'privacy:metadata:lvlcomp:levelid',
                'userid'             => 'privacy:metadata:lvlcomp:userid',
                'status'             => 'privacy:metadata:lvlcomp:status',
                'timecompleted'      => 'privacy:metadata:lvlcomp:timecompleted',
                'completedcourseids' => 'privacy:metadata:lvlcomp:completedcourseids',
                'source'             => 'privacy:metadata:lvlcomp:source',
                'timecreated'        => 'privacy:metadata:lvlcomp:timecreated',
                'timemodified'       => 'privacy:metadata:lvlcomp:timemodified',
            ],
            'privacy:metadata:lvlcomp');
        $collection->add_database_table(self::TRAINERS,
            [
                'programid'      => 'privacy:metadata:trainers:programid',
                'userid'         => 'privacy:metadata:trainers:userid',
                'feedbackid'     => 'privacy:metadata:trainers:feedbackid',
                'feedback_score' => 'privacy:metadata:trainers:feedback_score',
                'assignedby'     => 'privacy:metadata:trainers:assignedby',
                'timecreated'    => 'privacy:metadata:trainers:timecreated',
                'timemodified'   => 'privacy:metadata:trainers:timemodified',
            ],
            'privacy:metadata:trainers');
        $collection->add_database_table(self::TRAINERFB,
            [
                'programtrainerid' => 'privacy:metadata:trainerfb:programtrainerid',
                'programid'        => 'privacy:metadata:trainerfb:programid',
                'trainerid'        => 'privacy:metadata:trainerfb:trainerid',
                'userid'           => 'privacy:metadata:trainerfb:userid',
                'score'            => 'privacy:metadata:trainerfb:score',
                'timecreated'      => 'privacy:metadata:trainerfb:timecreated',
                'timemodified'     => 'privacy:metadata:trainerfb:timemodified',
            ],
            'privacy:metadata:trainerfb');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        foreach (self::PERSON_COLUMNS as $table => $columns) {
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if ($DB->record_exists($table, [$column => $userid])) {
                    $contextlist->add_system_context();
                    return $contextlist;
                }
            }
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $userid = (int) $contextlist->get_user()->id;
        $dbman = $DB->get_manager();

        $data = [];
        $data['enrolments'] = array_values((array) $DB->get_records(self::USERS, ['userid' => $userid]));
        if ($dbman->table_exists(self::LVLCOMP)) {
            $data['level_completions'] = array_values((array) $DB->get_records(self::LVLCOMP, ['userid' => $userid]));
        }
        if ($dbman->table_exists(self::TRAINERS)) {
            $data['trainer_assignments'] = array_values((array) $DB->get_records(self::TRAINERS, ['userid' => $userid]));
        }
        if ($dbman->table_exists(self::TRAINERFB)) {
            // Feedback about the person as a trainer, and feedback the person gave. The other party's id is not exported.
            $data['trainer_feedback_received'] = array_values((array) $DB->get_records(self::TRAINERFB,
                ['trainerid' => $userid], 'id ASC',
                'id, programtrainerid, programid, score, timecreated, timemodified'));
            $data['trainer_feedback_given'] = array_values((array) $DB->get_records(self::TRAINERFB,
                ['userid' => $userid], 'id ASC',
                'id, programtrainerid, programid, score, timecreated, timemodified'));
        }
        // Enrolments and trainer assignments the person made for others: what they did, not who it was done to.
        $data['enrolments_made'] = array_values((array) $DB->get_records(self::USERS,
            ['enrolledby' => $userid], 'id ASC', 'id, programid, timecreated'));
        if ($dbman->table_exists(self::TRAINERS)) {
            $data['trainer_assignments_made'] = array_values((array) $DB->get_records(self::TRAINERS,
                ['assignedby' => $userid], 'id ASC', 'id, programid, timecreated'));
        }

        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_programs'], (object) $data);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records(self::USERS);
        foreach ([self::LVLCOMP, self::TRAINERS, self::TRAINERFB] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $DB->delete_records($table);
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (!self::has_system_context($contextlist)) return;
        self::erase_users([(int) $contextlist->get_user()->id]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = [];
        foreach (self::PERSON_COLUMNS as $table => $columns) {
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            foreach ($columns as $column) {
                $userids = array_merge($userids,
                    $DB->get_fieldset_select($table, 'DISTINCT ' . $column, $column . ' > 0'));
            }
        }
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        self::erase_users(array_map('intval', $userids));
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it, so delete_data_for_user() above stays the
     * full erasure.
     *
     * Table-by-table (2026-09-24, extended 2026-09-30 for ADR-032):
     *   - local_sentientia_programs_users: KEEP. It is the
     *     certification-program enrolment AND completion record (status 2 =
     *     completed, with timecompleted; currentlevelid is how far the person
     *     got). A certification is exactly what an auditor asks to see after
     *     the person has gone, and the flow keeps the core course completions
     *     it was earned from, so deleting it here would leave those orphaned
     *     from the certificate they add up to. The one column that names
     *     ANOTHER person (enrolledby, who enrolled the learner) is cleared when
     *     that other person is the one being anonymised.
     *   - local_sentientia_programs_lvlcomp: KEEP, for the same reason: it is the
     *     level-by-level part of the same record, and it holds no free text.
     *   - local_sentientia_programs_trainers: KEEP the assignment (a record of who
     *     trained the program, keyed to the anonymised user row); clear
     *     assignedby.
     *   - local_sentientia_programs_trainerfb: KEEP the feedback about a trainer
     *     (trainerid stays, it is the trainer's record); clear userid, the
     *     learner who gave it, so the feedback stays and the giver goes.
     *   - local_sentientia_programs, _levels, _courses: program definitions
     *     with no user column. Nothing to erase or keep.
     *
     * So the record stays, and what goes is the person as the author of
     * somebody else's record. The method's existence is what stops the DPDP flow
     * calling delete_data_for_user() and destroying the certification record.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist) {
        if (!self::has_system_context($contextlist)) {
            return;
        }
        self::clear_references((int) $contextlist->get_user()->id);
    }

    /**
     * Erase the people in $userids (core's privacy API path): their own rows go, and the references to them in other
     * people's rows are cleared.
     *
     * @param int[] $userids
     */
    private static function erase_users(array $userids): void {
        global $DB;
        $dbman = $DB->get_manager();
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        $DB->delete_records_select(self::USERS, "userid $insql", $params);
        if ($dbman->table_exists(self::LVLCOMP)) {
            $DB->delete_records_select(self::LVLCOMP, "userid $insql", $params);
        }
        if ($dbman->table_exists(self::TRAINERS)) {
            // Feedback about an assignment of this trainer goes with the assignment; so does feedback about the
            // person as a trainer.
            if ($dbman->table_exists(self::TRAINERFB)) {
                $assignments = $DB->get_fieldset_select(self::TRAINERS, 'id', "userid $insql", $params);
                if ($assignments) {
                    [$ain, $aparams] = $DB->get_in_or_equal($assignments, SQL_PARAMS_NAMED, 'aid');
                    $DB->delete_records_select(self::TRAINERFB, "programtrainerid $ain", $aparams);
                }
                $DB->delete_records_select(self::TRAINERFB, "trainerid $insql", $params);
            }
            $DB->delete_records_select(self::TRAINERS, "userid $insql", $params);
        }
        foreach ($userids as $userid) {
            self::clear_references($userid);
        }
    }

    /**
     * Clear the references to a person that sit in other people's rows: who enrolled, who assigned, who gave feedback.
     *
     * @param int $userid
     */
    private static function clear_references(int $userid): void {
        global $DB;
        $dbman = $DB->get_manager();
        $DB->set_field(self::USERS, 'enrolledby', 0, ['enrolledby' => $userid]);
        if ($dbman->table_exists(self::TRAINERS)) {
            $DB->set_field(self::TRAINERS, 'assignedby', 0, ['assignedby' => $userid]);
        }
        if ($dbman->table_exists(self::TRAINERFB)) {
            // The giver of the feedback is optional; the feedback about the trainer stays.
            $DB->set_field_select(self::TRAINERFB, 'userid', null, 'userid = :uid', ['uid' => $userid]);
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}

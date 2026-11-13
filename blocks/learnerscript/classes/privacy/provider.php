<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for block_learnerscript.
 *
 * @package    block_learnerscript
 * @copyright  2018 Jahnavi <jahnavi@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnerscript\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

// SENTIENTIA-CORE-MOD (vendor): working privacy provider - see moodle-enhancement/docs/core-mods/2026-11-13-learnerscript-privacy-provider.md
/**
 * Privacy provider for block_learnerscript.
 *
 * The vendor provider (to 2019052008.6) declared block_devicels (IP address, country, region, city, device) but
 * reported no contexts and exported and deleted nothing, so neither a subject access request nor an erasure reached
 * any of it. Six more user tables were not declared at all (found by core's test_table_coverage). Rewritten
 * 2026-11-13 (Nitin: full fix as a recorded vendor patch).
 *
 * Every table is site-level, so the data lives in the system context.
 *
 * SUBJECT rows (deleted on erasure, exported):
 *   block_devicels            the user's access IP, location and device
 *   block_ls_coursetimestats  time the user spent per course
 *   block_ls_modtimestats     time the user spent per activity
 *   block_ls_learningformats  report cache: the user's enrolment, completion and deadlines per learning format
 *   block_ls_exams            report cache: the user's exams
 *   block_ls_certificates     report cache: the user's certificates
 * ACTOR columns (anonymised to 0 on erasure, the row stays, exported):
 *   block_ls_exams.usermodified, block_ls_certificates.usermodified
 *   block_ls_schedule.userid  who created a scheduled report (the schedule belongs to its report)
 * RECIPIENT list:
 *   block_ls_schedule.sendinguserid  comma-separated recipients; erasure removes the user's id, the schedule stays
 *                                    (the send loop already skips ids it cannot find)
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string[] Tables whose rows belong to the user in `userid`. */
    private const SUBJECT_TABLES = [
        'block_devicels',
        'block_ls_coursetimestats',
        'block_ls_modtimestats',
        'block_ls_learningformats',
        'block_ls_exams',
        'block_ls_certificates',
    ];

    /** @var string[] Tables with a `usermodified` actor column. */
    private const ACTOR_TABLES = ['block_ls_exams', 'block_ls_certificates'];

    /** @var string */
    private const SCHEDULE = 'block_ls_schedule';

    /**
     * Returns metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_devicels',
             [
                'userid' => 'privacy:metadata:block_devicels:userid',
                'accessip' => 'privacy:metadata:block_devicels:accessip',
                'country' => 'privacy:metadata:block_devicels:country',
                'countrycode' => 'privacy:metadata:block_devicels:countrycode',
                'region' => 'privacy:metadata:block_devicels:region',
                'regionname' => 'privacy:metadata:block_devicels:regionname',
                'city' => 'privacy:metadata:block_devicels:city',
                'browser' => 'privacy:metadata:block_devicels:browser',
                'browserparent' => 'privacy:metadata:block_devicels:browserparent',
                'platform' => 'privacy:metadata:block_devicels:platform',
                'browserversion' => 'privacy:metadata:block_devicels:browserversion',
                'devicetype' => 'privacy:metadata:block_devicels:devicetype',
                'pointingmethod' => 'privacy:metadata:block_devicels:pointingmethod',
                'ismobiledevice' => 'privacy:metadata:block_devicels:ismobiledevice',
                'istablet' => 'privacy:metadata:block_devicels:istablet',
                'timemodified' => 'privacy:metadata:block_devicels:timemodified',
             ],
            'privacy:metadata:block_devicels'
        );
        $collection->add_database_table('block_ls_schedule', [
            'userid'        => 'privacy:metadata:block_ls_schedule:userid',
            'sendinguserid' => 'privacy:metadata:block_ls_schedule:sendinguserid',
            'reportid'      => 'privacy:metadata:block_ls_schedule:reportid',
            'exportformat'  => 'privacy:metadata:block_ls_schedule:exportformat',
            'frequency'     => 'privacy:metadata:block_ls_schedule:frequency',
            'timecreated'   => 'privacy:metadata:ls:timecreated',
        ], 'privacy:metadata:block_ls_schedule');
        $collection->add_database_table('block_ls_coursetimestats', [
            'userid'      => 'privacy:metadata:ls:userid',
            'courseid'    => 'privacy:metadata:ls:courseid',
            'timespent'   => 'privacy:metadata:ls:timespent',
            'timecreated' => 'privacy:metadata:ls:timecreated',
        ], 'privacy:metadata:block_ls_coursetimestats');
        $collection->add_database_table('block_ls_modtimestats', [
            'userid'      => 'privacy:metadata:ls:userid',
            'courseid'    => 'privacy:metadata:ls:courseid',
            'activityid'  => 'privacy:metadata:block_ls_modtimestats:activityid',
            'timespent'   => 'privacy:metadata:ls:timespent',
            'timecreated' => 'privacy:metadata:ls:timecreated',
        ], 'privacy:metadata:block_ls_modtimestats');
        $collection->add_database_table('block_ls_learningformats', [
            'userid'           => 'privacy:metadata:ls:userid',
            'username'         => 'privacy:metadata:ls:username',
            'name'             => 'privacy:metadata:block_ls_learningformats:name',
            'enroldate'        => 'privacy:metadata:ls:enroldate',
            'completiondate'   => 'privacy:metadata:ls:completiondate',
            'upcomingdeadline' => 'privacy:metadata:ls:deadline',
            'overduedeadline'  => 'privacy:metadata:block_ls_learningformats:overduedeadline',
        ], 'privacy:metadata:block_ls_learningformats');
        $collection->add_database_table('block_ls_exams', [
            'userid'         => 'privacy:metadata:ls:userid',
            'username'       => 'privacy:metadata:ls:username',
            'examname'       => 'privacy:metadata:block_ls_exams:examname',
            'enroldate'      => 'privacy:metadata:ls:enroldate',
            'completiondate' => 'privacy:metadata:ls:completiondate',
            'deadline'       => 'privacy:metadata:ls:deadline',
            'usermodified'   => 'privacy:metadata:ls:usermodified',
        ], 'privacy:metadata:block_ls_exams');
        $collection->add_database_table('block_ls_certificates', [
            'userid'          => 'privacy:metadata:ls:userid',
            'username'        => 'privacy:metadata:ls:username',
            'certificatename' => 'privacy:metadata:block_ls_certificates:certificatename',
            'courseid'        => 'privacy:metadata:ls:courseid',
            'enroldate'       => 'privacy:metadata:ls:enroldate',
            'completiondate'  => 'privacy:metadata:ls:completiondate',
            'deadline'        => 'privacy:metadata:ls:deadline',
            'usermodified'    => 'privacy:metadata:ls:usermodified',
        ], 'privacy:metadata:block_ls_certificates');
        return $collection;
    }

    /**
     * The system context, when the user appears anywhere in the plugin's tables.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $found = false;
        foreach (self::SUBJECT_TABLES as $table) {
            $found = $found || $DB->record_exists($table, ['userid' => $userid]);
        }
        foreach (self::ACTOR_TABLES as $table) {
            $found = $found || $DB->record_exists($table, ['usermodified' => $userid]);
        }
        $found = $found || $DB->record_exists(self::SCHEDULE, ['userid' => $userid]);
        $found = $found || !empty(self::schedules_sent_to([$userid]));
        if ($found) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Everyone named in the plugin's tables.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach (self::SUBJECT_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid > 0", []);
        }
        foreach (self::ACTOR_TABLES as $table) {
            $userlist->add_from_sql('usermodified', "SELECT usermodified FROM {{$table}} WHERE usermodified > 0", []);
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {" . self::SCHEDULE . "} WHERE userid > 0", []);
        $recipients = [];
        $rs = $DB->get_recordset(self::SCHEDULE, null, '', 'id, sendinguserid');
        foreach ($rs as $row) {
            $recipients = array_merge($recipients, self::recipients($row->sendinguserid));
        }
        $rs->close();
        if ($recipients) {
            $userlist->add_users(array_values(array_unique($recipients)));
        }
    }

    /**
     * Every row that names the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $context = \context_system::instance();
        if (!in_array((int) $context->id, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $root = get_string('pluginname', 'block_learnerscript');

        foreach (self::SUBJECT_TABLES as $table) {
            $rows = array_values(array_map(fn($r) => (array) $r, $DB->get_records($table, ['userid' => $userid], 'id ASC')));
            if ($rows) {
                writer::with_context($context)->export_data([$root, $table], (object) ['rows' => $rows]);
            }
        }
        foreach (self::ACTOR_TABLES as $table) {
            $rows = $DB->get_records_select($table, 'usermodified = :u AND userid <> :u2',
                ['u' => $userid, 'u2' => $userid], 'id ASC', 'id, userid, usermodified, timecreated');
            if ($rows) {
                writer::with_context($context)->export_data([$root, $table . '_modified'],
                    (object) ['rows' => array_values(array_map(fn($r) => (array) $r, $rows))]);
            }
        }
        $schedules = [];
        $mine = $DB->get_records(self::SCHEDULE, ['userid' => $userid], 'id ASC',
            'id, reportid, userid, exportformat, frequency, timecreated');
        foreach ($mine as $r) {
            $schedules[$r->id] = (array) $r + ['role' => 'creator'];
        }
        foreach (self::schedules_sent_to([$userid]) as $r) {
            $schedules[$r->id] = ['id' => $r->id, 'reportid' => $r->reportid,
                'role' => isset($schedules[$r->id]) ? 'creator and recipient' : 'recipient'] + ($schedules[$r->id] ?? []);
        }
        if ($schedules) {
            writer::with_context($context)->export_data([$root, self::SCHEDULE],
                (object) ['schedules' => array_values($schedules)]);
        }
    }

    /**
     * Context-wide erasure: delete every subject row, anonymise every actor and empty every recipient list.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        foreach (self::SUBJECT_TABLES as $table) {
            $DB->delete_records($table);
        }
        $DB->set_field(self::SCHEDULE, 'userid', 0, []);
        $DB->set_field(self::SCHEDULE, 'sendinguserid', '', []);
    }

    /**
     * Erase one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $context = \context_system::instance();
        if (!in_array((int) $context->id, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }
        self::erase([(int) $contextlist->get_user()->id]);
    }

    /**
     * Erase the listed users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = array_map('intval', $userlist->get_userids());
        if ($userids) {
            self::erase($userids);
        }
    }

    /**
     * Delete the users' own rows, anonymise them as actors, take them off recipient lists.
     *
     * @param int[] $userids
     */
    private static function erase(array $userids): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        foreach (self::SUBJECT_TABLES as $table) {
            $DB->delete_records_select($table, "userid {$insql}", $params);
        }
        foreach (self::ACTOR_TABLES as $table) {
            $DB->set_field_select($table, 'usermodified', 0, "usermodified {$insql}", $params);
        }
        $DB->set_field_select(self::SCHEDULE, 'userid', 0, "userid {$insql}", $params);
        foreach (self::schedules_sent_to($userids) as $row) {
            $kept = array_diff(self::recipients($row->sendinguserid), $userids);
            $DB->set_field(self::SCHEDULE, 'sendinguserid', implode(',', $kept), ['id' => $row->id]);
        }
    }

    /**
     * The schedules whose recipient list names any of the users.
     *
     * @param int[] $userids
     * @return \stdClass[]
     */
    private static function schedules_sent_to(array $userids): array {
        global $DB;
        $found = [];
        $rs = $DB->get_recordset(self::SCHEDULE, null, 'id ASC', 'id, reportid, sendinguserid');
        foreach ($rs as $row) {
            if (array_intersect(self::recipients($row->sendinguserid), $userids)) {
                $found[$row->id] = $row;
            }
        }
        $rs->close();
        return $found;
    }

    /**
     * The user ids in a comma-separated recipient list.
     *
     * @param string|null $list
     * @return int[]
     */
    private static function recipients(?string $list): array {
        $ids = array_map('intval', array_map('trim', explode(',', (string) $list)));
        return array_values(array_filter($ids, fn($id) => $id > 0));
    }
}
// END SENTIENTIA-CORE-MOD

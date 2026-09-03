<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_sentientia_xapi.
 *
 * Handles GDPR data subject rights (export and erasure) for xAPI
 * statements and cmi5 sessions stored by this plugin.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_xapi_stmts',
            [
                'actorid'    => 'privacy:metadata:local_sentientia_xapi_statements:actorid',
                'actor'      => 'privacy:metadata:local_sentientia_xapi_statements:actor',
                'verb'       => 'privacy:metadata:local_sentientia_xapi_statements:verb',
                'object'     => 'privacy:metadata:local_sentientia_xapi_statements:object',
                'result'     => 'privacy:metadata:local_sentientia_xapi_statements:result',
                'context'    => 'privacy:metadata:local_sentientia_xapi_statements:context',
                'timestored' => 'privacy:metadata:local_sentientia_xapi_statements:timestored',
            ],
            'privacy:metadata:local_sentientia_xapi_statements'
        );

        $collection->add_database_table(
            'local_sentientia_xapi_cmi5',
            [
                'userid'       => 'privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid',
                'registration' => 'privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration',
            ],
            'privacy:metadata:local_sentientia_xapi_cmi5_sessions'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // xAPI data is stored at system level.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT actorid FROM {local_sentientia_xapi_stmts} WHERE actorid IS NOT NULL";
        $userlist->add_from_sql('actorid', $sql, []);

        $sql2 = "SELECT DISTINCT userid FROM {local_sentientia_xapi_cmi5}";
        $userlist->add_from_sql('userid', $sql2, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $statements = $DB->get_records_select(
            'local_sentientia_xapi_stmts',
            'actorid = :uid',
            ['uid' => $userid],
            'timestored DESC',
            'statementid, verb, objectid, score_scaled, success, completion, timestored'
        );

        writer::with_context(\context_system::instance())->export_data(
            ['local_sentientia_xapi', 'statements'],
            (object) ['statements' => array_values($statements)]
        );

        $sessions = $DB->get_records(
            'local_sentientia_xapi_cmi5',
            ['userid' => $userid],
            'timecreated DESC',
            'registration, status, score_scaled, success, duration, timecreated'
        );

        writer::with_context(\context_system::instance())->export_data(
            ['local_sentientia_xapi', 'cmi5_sessions'],
            (object) ['sessions' => array_values($sessions)]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_xapi_stmts', []);
        $DB->delete_records('local_sentientia_xapi_cmi5', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Null-out the actorid but keep the statement for LRS integrity.
        // (Deleting statements would break VoidedStatement chains.)
        $DB->set_field('local_sentientia_xapi_stmts', 'actorid', null, ['actorid' => $userid]);
        $DB->set_field('local_sentientia_xapi_stmts', 'actor',
            json_encode(['objectType' => 'Agent', 'account' => ['homePage' => 'redacted', 'name' => 'redacted']]),
            ['actorid' => null]);

        // cmi5 sessions carry no learning content — safe to delete.
        $DB->delete_records('local_sentientia_xapi_cmi5', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->set_field_select('local_sentientia_xapi_stmts', 'actorid', null, "actorid $insql", $params);
        $DB->delete_records_select('local_sentientia_xapi_cmi5', "userid $insql", $params);
    }
}

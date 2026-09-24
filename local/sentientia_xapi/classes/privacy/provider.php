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
        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }

        // Keep the statement for LRS integrity (deleting would break
        // VoidedStatement chains): redact its actor, then drop the link.
        // ORDER MATTERS. Until 2026-09-24 the link was dropped first and the
        // redaction then ran on `actorid IS NULL` - every statement in the LRS
        // whose actor never resolved to a local user, across all tenants.
        self::redact_actor($userid);
        $DB->set_field('local_sentientia_xapi_stmts', 'actorid', null, ['actorid' => $userid]);

        $DB->delete_records('local_sentientia_xapi_cmi5', ['userid' => $userid]);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        // The actor JSON holds the learner's own identifiers (mbox, account
        // name), which anonymising the user row does not reach. actorid stays,
        // so the kept statements remain attributable to the anonymised row.
        self::redact_actor($userid);
        // A cmi5 row is an attempt record (status, score, success): keep it,
        // drop only the launch credentials.
        $DB->set_field('local_sentientia_xapi_cmi5', 'launchtoken', null, ['userid' => $userid]);
        $DB->set_field('local_sentientia_xapi_cmi5', 'sessionid', null, ['userid' => $userid]);
    }

    /**
     * Replace the actor JSON of this user's statements - and only theirs.
     *
     * "Theirs" is actorid = $userid, which is only as trustworthy as
     * \local_sentientia_xapi\lrs\store::resolve_actor_userid(): since
     * 2026-09-24 it maps an LRS-posted actor to a local user only on this
     * site's account homePage and only inside the posting client's tenant.
     */
    private static function redact_actor(int $userid): void {
        global $DB;
        $DB->set_field('local_sentientia_xapi_stmts', 'actor',
            json_encode(['objectType' => 'Agent', 'account' => ['homePage' => 'redacted', 'name' => 'redacted']]),
            ['actorid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        // Redact before unlinking; this path never redacted at all before.
        $DB->set_field_select('local_sentientia_xapi_stmts', 'actor',
            json_encode(['objectType' => 'Agent', 'account' => ['homePage' => 'redacted', 'name' => 'redacted']]),
            "actorid $insql", $params);
        $DB->set_field_select('local_sentientia_xapi_stmts', 'actorid', null, "actorid $insql", $params);
        $DB->delete_records_select('local_sentientia_xapi_cmi5', "userid $insql", $params);
    }
}

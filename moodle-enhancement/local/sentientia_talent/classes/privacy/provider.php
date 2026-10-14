<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_sentientia_talent.
 *
 * PII-bearing tables:
 *   - local_sentientia_talent_succ  (candidateid / incumbentid / usermodified)
 *   - local_sentientia_talent_int   (userid — expressions of interest)
 *   - local_sentientia_talent_opp   (postedby)
 *   - local_sentientia_talent_audit (changedby / targetuserid)
 *
 * Reference tables (career paths) store designation strings only and
 * carry no user-identifying data beyond the usermodified editor id, which
 * is handled here too.
 *
 * ACTOR COLUMN usermodified (owner decision, 2026-10-07, "privacy: actor columns", rule R9 of the BizLMS import).
 *   - local_sentientia_talent_path / _succ / _opp record the user who last edited a row. local_sentientia_talent_path was
 *     not declared at all, and usermodified of the other two was handled but not declared. The rows belong to the
 *     tenant's talent configuration and to the people they name, not to the editor, so an erasure KEEPS them and sets
 *     usermodified to 0 (the signed users.erasure_treatment = anonymise design for actor columns). The export lists
 *     what the user last edited (table, id, when), never the notes or the nominee.
 *
 * @package local_sentientia_talent
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** Tables that record the user who last edited a row (usermodified). */
    private const EDITED_TABLES = ['local_sentientia_talent_path', 'local_sentientia_talent_succ', 'local_sentientia_talent_opp'];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_talent_path', [
            'name'         => 'privacy:metadata:path:name',
            'usermodified' => 'privacy:metadata:path:usermodified',
            'timemodified' => 'privacy:metadata:path:timemodified',
        ], 'privacy:metadata:path');

        $collection->add_database_table('local_sentientia_talent_succ', [
            'designation' => 'privacy:metadata:succ:designation',
            'candidateid' => 'privacy:metadata:succ:candidateid',
            'incumbentid' => 'privacy:metadata:succ:incumbentid',
            'readiness'   => 'privacy:metadata:succ:readiness',
            'notes'       => 'privacy:metadata:succ:notes',
            'usermodified' => 'privacy:metadata:succ:usermodified',
            'timecreated' => 'privacy:metadata:succ:timecreated',
        ], 'privacy:metadata:succ');

        $collection->add_database_table('local_sentientia_talent_int', [
            'opportunityid' => 'privacy:metadata:int:opportunityid',
            'userid'        => 'privacy:metadata:int:userid',
            'message'       => 'privacy:metadata:int:message',
            'matchpct'      => 'privacy:metadata:int:matchpct',
            'timecreated'   => 'privacy:metadata:int:timecreated',
        ], 'privacy:metadata:int');

        $collection->add_database_table('local_sentientia_talent_opp', [
            'title'       => 'privacy:metadata:opp:title',
            'postedby'    => 'privacy:metadata:opp:postedby',
            'usermodified' => 'privacy:metadata:opp:usermodified',
            'timecreated' => 'privacy:metadata:opp:timecreated',
        ], 'privacy:metadata:opp');

        $collection->add_database_table('local_sentientia_talent_audit', [
            'action'       => 'privacy:metadata:audit:action',
            'targetuserid' => 'privacy:metadata:audit:targetuserid',
            'changedby'    => 'privacy:metadata:audit:changedby',
            'timecreated'  => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All talent data lives at system context.
        $contextlist->add_from_sql(
            "SELECT id FROM {context} WHERE contextlevel = :sys",
            ['sys' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('candidateid',
            "SELECT DISTINCT candidateid FROM {local_sentientia_talent_succ}", []);
        $userlist->add_from_sql('incumbentid',
            "SELECT DISTINCT incumbentid FROM {local_sentientia_talent_succ}
              WHERE incumbentid IS NOT NULL", []);
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT userid FROM {local_sentientia_talent_int}", []);
        $userlist->add_from_sql('postedby',
            "SELECT DISTINCT postedby FROM {local_sentientia_talent_opp}", []);
        $userlist->add_from_sql('changedby',
            "SELECT DISTINCT changedby FROM {local_sentientia_talent_audit}", []);
        $userlist->add_from_sql('targetuserid',
            "SELECT DISTINCT targetuserid FROM {local_sentientia_talent_audit}
              WHERE targetuserid IS NOT NULL", []);
        foreach (self::EDITED_TABLES as $table) {
            $userlist->add_from_sql('usermodified',
                "SELECT DISTINCT usermodified FROM {{$table}} WHERE usermodified > 0", []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Succession nominations naming this user (as candidate or incumbent).
        $succ = $DB->get_records_sql(
            "SELECT * FROM {local_sentientia_talent_succ}
              WHERE candidateid = :u1 OR incumbentid = :u2",
            ['u1' => $userid, 'u2' => $userid]);
        if (!empty($succ)) {
            $entries = array_map(fn($r) => (object) [
                'designation'  => format_string($r->designation),
                'role'         => ((int) $r->candidateid === $userid) ? 'candidate' : 'incumbent',
                'readiness'    => $r->readiness,
                'time_recorded' => (int) $r->timecreated,
            ], $succ);
            writer::with_context($context)->export_data(
                ['Talent — succession plans referencing me'],
                (object) ['nominations' => array_values($entries)]);
        }

        // Expressions of interest.
        $int = $DB->get_records('local_sentientia_talent_int', ['userid' => $userid]);
        if (!empty($int)) {
            $entries = array_map(fn($r) => (object) [
                'opportunityid' => (int) $r->opportunityid,
                'message'       => (string) ($r->message ?? ''),
                'matchpct'      => (int) $r->matchpct,
                'time_recorded' => (int) $r->timecreated,
            ], $int);
            writer::with_context($context)->export_data(
                ['Talent — my expressions of interest'],
                (object) ['interests' => array_values($entries)]);
        }

        // Records this user last edited: which table, which row and when. Never the notes or the person a row names.
        $edited = [];
        foreach (self::EDITED_TABLES as $table) {
            foreach ($DB->get_records_select($table, 'usermodified = :uid', ['uid' => $userid], 'id', 'id, timemodified') as $r) {
                $edited[] = (object) [
                    'table'         => $table,
                    'id'            => (int) $r->id,
                    'time_modified' => (int) $r->timemodified,
                ];
            }
        }
        if (!empty($edited)) {
            writer::with_context($context)->export_data(
                ['Talent — records I last edited'],
                (object) ['records' => $edited]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_sentientia_talent_succ');
        $DB->delete_records('local_sentientia_talent_int');
        $DB->delete_records('local_sentientia_talent_audit');
        // Opportunities are reference-ish; null out the poster rather than
        // deleting the postings on a blanket context wipe.
        $DB->set_field('local_sentientia_talent_opp', 'postedby', 0, []);
        // Career paths and postings stay with the tenant; no editor stays on them.
        $DB->set_field_select('local_sentientia_talent_path', 'usermodified', 0, 'usermodified <> 0');
        $DB->set_field_select('local_sentientia_talent_opp', 'usermodified', 0, 'usermodified <> 0');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $uid = $contextlist->get_user()->id;
        // Remove nominations naming this user as candidate.
        $DB->delete_records('local_sentientia_talent_succ', ['candidateid' => $uid]);
        // Null the incumbent reference where this user was the incumbent.
        $DB->set_field('local_sentientia_talent_succ', 'incumbentid', null,
            ['incumbentid' => $uid]);
        // Remove the user's own expressions of interest.
        $DB->delete_records('local_sentientia_talent_int', ['userid' => $uid]);
        // Null actor/target references in the audit log (keep the trail row
        // but detach the id from the erased subject).
        $DB->set_field('local_sentientia_talent_audit', 'targetuserid', null,
            ['targetuserid' => $uid]);
        $DB->set_field('local_sentientia_talent_audit', 'changedby', 0,
            ['changedby' => $uid]);
        $DB->set_field('local_sentientia_talent_opp', 'postedby', 0,
            ['postedby' => $uid]);
        // The editor is removed from what they last edited; the rows stay.
        foreach (self::EDITED_TABLES as $table) {
            $DB->set_field($table, 'usermodified', 0, ['usermodified' => $uid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute("DELETE FROM {local_sentientia_talent_succ} WHERE candidateid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_talent_succ} SET incumbentid = NULL
                       WHERE incumbentid $insql", $inparams);
        $DB->execute("DELETE FROM {local_sentientia_talent_int} WHERE userid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_talent_audit} SET targetuserid = NULL
                       WHERE targetuserid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_talent_audit} SET changedby = 0
                       WHERE changedby $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_talent_opp} SET postedby = 0
                       WHERE postedby $insql", $inparams);
        foreach (self::EDITED_TABLES as $table) {
            $DB->execute("UPDATE {{$table}} SET usermodified = 0 WHERE usermodified $insql", $inparams);
        }
    }
}

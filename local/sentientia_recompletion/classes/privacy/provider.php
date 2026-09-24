<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_sentientia_recompletion.
 *
 * Tables, and what each erasure does to them (2026-09-24):
 *
 *   local_sentientia_recompletion_rules
 *       Rule definitions. No user column. Untouched.
 *
 *   local_sentientia_recompletion_history
 *       The append-only reset audit log, and a COMPLIANCE record: a reset
 *       deletes the course_completions row (and, per rule, the grades and
 *       quiz attempts), so for every cycle before the current one this row
 *       (previous_timecompleted) is the only surviving evidence that the
 *       person completed the course - the annual AML / KYC / POSH
 *       attestations an auditor asks for.
 *       - Subject column `userid`:
 *           core erasure (delete_data_for_user) redacts it to 0, as it always
 *           has; the row survives, attributable to nobody.
 *           DPDP erasure (anonymise_data_for_user) KEEPS it: the flow promises
 *           to retain compliance records keyed to the user row it anonymises
 *           in place. Redacting to 0 there, which is what happened while this
 *           provider had no anonymise hook, detached every earlier cycle's
 *           completion from the anonymised person and pooled it with every
 *           other erased user's rows under 0.
 *       - Actor column `reset_by_userid` (the admin who pressed reset on
 *           somebody else's completion; NULL = the scheduled task):
 *           anonymised to 0 by both erasures. Never used to delete a row,
 *           because the row is the other person's record.
 *       No free-text column: `reason` is a cron|manual|bulk code.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** The reset audit log. */
    private const TABLE_HISTORY = 'local_sentientia_recompletion_history';

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_recompletion_rules', [],
            'privacy:metadata:local_sentientia_recompletion_rules');
        $collection->add_database_table(self::TABLE_HISTORY, [
            'userid'   => 'privacy:metadata:local_sentientia_recompletion_history:userid',
            'courseid' => 'privacy:metadata:local_sentientia_recompletion_history:courseid',
            'reason'   => 'privacy:metadata:local_sentientia_recompletion_history:reason',
            'reset_by_userid'
                => 'privacy:metadata:local_sentientia_recompletion_history:reset_by_userid',
            'previous_timecompleted'
                => 'privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted',
            'timecreated'
                => 'privacy:metadata:local_sentientia_recompletion_history:timecreated',
        ], 'privacy:metadata:local_sentientia_recompletion_history');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        // An admin who only ever reset other people's completions holds data
        // here too (the actor column), and must be reachable by an erasure.
        if ($DB->record_exists(self::TABLE_HISTORY, ['userid' => $userid])
                || $DB->record_exists(self::TABLE_HISTORY, ['reset_by_userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        // userid 0 is a row an earlier erasure redacted, not a user.
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_recompletion_history} WHERE userid > 0", []);
        $userlist->add_from_sql('reset_by_userid',
            "SELECT reset_by_userid FROM {local_sentientia_recompletion_history}
              WHERE reset_by_userid > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $context = \context_system::instance();
        $root = get_string('pluginname', 'local_sentientia_recompletion');

        $rows = $DB->get_records(self::TABLE_HISTORY, ['userid' => $userid], 'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'course_id'    => $r->courseid,
                'reason'       => $r->reason,
                'reset_at'     => userdate($r->timecreated),
                'previous_completion' => $r->previous_timecompleted
                    ? userdate($r->previous_timecompleted) : null,
                'dryrun'       => (bool) $r->dryrun,
            ];
        }
        if (!empty($out)) {
            writer::with_context($context)->export_data([$root],
                (object) ['recompletion_history' => $out]);
        }

        // Resets this person performed on other people's completions. The
        // other person's id is deliberately not exported: it is their data.
        $performed = $DB->get_records(self::TABLE_HISTORY, ['reset_by_userid' => $userid],
            'timecreated DESC', 'id, courseid, reason, timecreated');
        $acts = [];
        foreach ($performed as $r) {
            $acts[] = [
                'course_id' => $r->courseid,
                'reason'    => $r->reason,
                'reset_at'  => userdate($r->timecreated),
            ];
        }
        if (!empty($acts)) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:export:resets_performed', 'local_sentientia_recompletion')],
                (object) ['resets_performed' => $acts]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        // Compliance: history is required audit. Redact only.
        $DB->set_field(self::TABLE_HISTORY, 'userid', 0, []);
        // NULL means "the scheduled task", which is nobody: leave it NULL.
        $DB->set_field_select(self::TABLE_HISTORY, 'reset_by_userid', 0,
            'reset_by_userid IS NOT NULL', []);
    }

    /**
     * Core's erasure (tool_dataprivacy). Redacts rather than deletes: see the
     * class comment.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::redact_for_user((int) $contextlist->get_user()->id);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $u) {
            self::redact_for_user((int) $u);
        }
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it, so delete_data_for_user() stays the full
     * erasure.
     *
     * The subject's own reset history is kept exactly as it is (see the class
     * comment for why it is a compliance record); only the actor column, where
     * the subject reset someone else's completion, is anonymised.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $DB->set_field(self::TABLE_HISTORY, 'reset_by_userid', 0, ['reset_by_userid' => $userid]);
    }

    private static function redact_for_user(int $userid): void {
        global $DB;
        // Redact userid (= 0 = "(redacted)" on history.php), preserve courseid
        // + reason + reset_at for compliance retention.
        $DB->set_field(self::TABLE_HISTORY, 'userid', 0, ['userid' => $userid]);
        // Actor column: anonymise, never delete - the row is someone else's.
        $DB->set_field(self::TABLE_HISTORY, 'reset_by_userid', 0, ['reset_by_userid' => $userid]);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel == CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }
}

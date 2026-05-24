<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Privacy provider for local_sentientia_calendar.
 *
 * The only personal data this plugin stores is one row per user in
 * local_sentientia_calendar_token. That row holds:
 *   - userid (FK to mdl_user.id)
 *   - the token itself (64 random chars — not derived from PII, but
 *     functionally identifies the user to anyone holding it)
 *   - last_used_ip, last_used_at, use_count (audit trail)
 *
 * Export: dump the row as JSON.
 * Delete: drop the row.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_calendar_token',
            [
                'userid'        => 'privacy:metadata:token:userid',
                'token'         => 'privacy:metadata:token:token',
                'last_used_at'  => 'privacy:metadata:token:last_used_at',
                'last_used_ip'  => 'privacy:metadata:token:last_used_ip',
                'use_count'     => 'privacy:metadata:token:use_count',
                'timecreated'   => 'privacy:metadata:token:timecreated',
                'timemodified'  => 'privacy:metadata:token:timemodified',
            ],
            'privacy:metadata:token'
        );
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        if ($DB->record_exists('local_sentientia_calendar_token', ['userid' => $userid])) {
            // Tokens live under the user's own user context (matches the
            // capability contextlevel in db/access.php).
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;
        if ($DB->record_exists('local_sentientia_calendar_token', ['userid' => $userid])) {
            $userlist->add_user($userid);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int) $context->instanceid !== $userid) {
                continue;
            }
            $rows = $DB->get_records('local_sentientia_calendar_token',
                ['userid' => $userid]);
            $export = [];
            foreach ($rows as $row) {
                $export[] = [
                    // We do NOT export the token itself — it's a secret
                    // that authenticates the user's feed. Exporting it
                    // would create a copy in the user's exported archive
                    // (typically downloaded to their PC), broadening the
                    // attack surface. Knowing IT EXISTS is enough.
                    'token_exists' => true,
                    'token_id'     => (int) $row->id,
                    'revoked'      => (bool) $row->revoked,
                    'last_used_at' => (int) $row->last_used_at,
                    'last_used_ip' => (string) ($row->last_used_ip ?? ''),
                    'use_count'    => (int) $row->use_count,
                    'timecreated'  => (int) $row->timecreated,
                    'timemodified' => (int) $row->timemodified,
                ];
            }
            if (!empty($export)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_calendar')],
                    (object) ['tokens' => $export]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_calendar_token',
            ['userid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int) $context->instanceid === $userid) {
                $DB->delete_records('local_sentientia_calendar_token',
                    ['userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_sentientia_calendar_token',
            "userid $insql", $params);
    }
}

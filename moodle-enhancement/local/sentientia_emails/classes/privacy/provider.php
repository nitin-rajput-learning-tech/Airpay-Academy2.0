<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for the notification log.
 *
 * ADR-032 (2026-09-30): the log also holds the e-mail history imported from
 * BizLMS. An imported row names TWO people: the recipient (userid) and the user who
 * queued the message (sender_userid), and it may carry the message body
 * (body_html, credentials already redacted by the importer). So:
 *
 *  - the recipient is a data subject of the whole row: export includes the body,
 *    and erasing the recipient deletes their rows, as before;
 *  - the sender is a data subject of the rows that name them as sender, on OTHER
 *    users' rows. Export lists those rows (when, never the recipient or the body,
 *    which belong to somebody else). Erasing the sender does NOT delete the
 *    recipients' history: it anonymises sender_userid to 0 on those rows.
 *
 * Two configuration tables record WHO last edited a row (owner decision, 2026-10-07, "privacy: actor columns", rule R9 of
 * the BizLMS import): local_sentientia_email_overrides and local_sentientia_email_rules, column usermodified. They hold a
 * tenant's e-mail templates and notification rules, which belong to the tenant and are kept when a person is erased; the
 * person is removed from them (usermodified set to 0, the signed users.erasure_treatment = anonymise design for actor
 * columns). The export lists what the user edited (ids, template key or rule name, when), never a template body.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** The delivery log. */
    private const LOG = 'local_sentientia_email_log';

    /** Per-tenant template overrides (actor column usermodified). */
    private const OVERRIDES = 'local_sentientia_email_overrides';

    /** Notification rules (actor column usermodified). */
    private const RULES = 'local_sentientia_email_rules';

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::LOG,
            [
                'userid'       => 'privacy:metadata:emaillog:userid',
                'subject'      => 'privacy:metadata:emaillog:subject',
                'recipient'    => 'privacy:metadata:emaillog:recipient',
                'status'       => 'privacy:metadata:emaillog:status',
                'timecreated'  => 'privacy:metadata:emaillog:timecreated',
                'sender_userid' => 'privacy:metadata:emaillog:sender_userid',
                'body_html'    => 'privacy:metadata:emaillog:body_html',
                'timesent'     => 'privacy:metadata:emaillog:timesent',
            ],
            'privacy:metadata:emaillog');
        $collection->add_database_table('local_sentientia_email_prefs',
            [
                'userid'       => 'privacy:metadata:emailprefs:userid',
                'timemodified' => 'privacy:metadata:emailprefs:timemodified',
            ],
            'privacy:metadata:emailprefs');
        $collection->add_database_table(self::OVERRIDES,
            [
                'template_key' => 'privacy:metadata:emailoverrides:template_key',
                'usermodified' => 'privacy:metadata:emailoverrides:usermodified',
                'timemodified' => 'privacy:metadata:emailoverrides:timemodified',
            ],
            'privacy:metadata:emailoverrides');
        $collection->add_database_table(self::RULES,
            [
                'rule_name'    => 'privacy:metadata:emailrules:rule_name',
                'usermodified' => 'privacy:metadata:emailrules:usermodified',
                'timemodified' => 'privacy:metadata:emailrules:timemodified',
            ],
            'privacy:metadata:emailrules');
        return $collection;
    }

    /**
     * Does this user appear as the last editor of an override or a rule?
     *
     * @param int $userid
     * @return bool
     */
    private static function edited_configuration(int $userid): bool {
        global $DB;
        foreach ([self::OVERRIDES, self::RULES] as $table) {
            if ($DB->get_manager()->table_exists($table) && $DB->record_exists($table, ['usermodified' => $userid])) {
                return true;
            }
        }
        return false;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $hit = self::edited_configuration($userid);
        if (!$hit && $DB->get_manager()->table_exists(self::LOG)
            && ($DB->record_exists(self::LOG, ['userid' => $userid])
                || $DB->record_exists(self::LOG, ['sender_userid' => $userid]))) {
            $hit = true;
        }
        if (!$hit && $DB->get_manager()->table_exists('local_sentientia_email_prefs')
            && $DB->record_exists('local_sentientia_email_prefs', ['userid' => $userid])) {
            $hit = true;
        }
        if ($hit) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $payload = ['userid' => $uid];
        if ($DB->get_manager()->table_exists(self::LOG)) {
            // Their own rows, whole: they are the recipient.
            $payload['log'] = array_values(
                (array) $DB->get_records(self::LOG, ['userid' => $uid], 'timecreated DESC'));
            // Other users' rows that name them as the sender: when, never the recipient or the body.
            $payload['log_as_sender'] = array_values((array) $DB->get_records_select(
                self::LOG, 'sender_userid = :uid AND userid <> :uid2', ['uid' => $uid, 'uid2' => $uid],
                'timecreated DESC', 'id, legacy_type, status, timecreated, timesent'));
        }
        if ($DB->get_manager()->table_exists('local_sentientia_email_prefs')) {
            $payload['prefs'] = $DB->get_record('local_sentientia_email_prefs',
                ['userid' => $uid]);
        }
        // The configuration this user last edited: which row and when, never the template text or the rule's conditions.
        if ($DB->get_manager()->table_exists(self::OVERRIDES)) {
            $payload['overrides_edited'] = array_values((array) $DB->get_records_select(self::OVERRIDES,
                'usermodified = :uid', ['uid' => $uid], 'timemodified DESC',
                'id, tenant_id, template_key, is_active, timemodified'));
        }
        if ($DB->get_manager()->table_exists(self::RULES)) {
            $payload['rules_edited'] = array_values((array) $DB->get_records_select(self::RULES,
                'usermodified = :uid', ['uid' => $uid], 'timemodified DESC',
                'id, tenant_id, rule_name, rule_type, enabled, timemodified'));
        }
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_emails'], (object) $payload);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        if ($DB->get_manager()->table_exists(self::LOG)) {
            $DB->delete_records(self::LOG);
        }
        if ($DB->get_manager()->table_exists('local_sentientia_email_prefs')) {
            $DB->delete_records('local_sentientia_email_prefs');
        }
        // Configuration is the tenant's, not the editor's: kept, with every editor removed.
        foreach ([self::OVERRIDES, self::RULES] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $DB->set_field_select($table, 'usermodified', 0, 'usermodified <> 0');
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        foreach ([self::OVERRIDES, self::RULES] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $DB->set_field_select($table, 'usermodified', 0, 'usermodified = :uid', ['uid' => $uid]);
            }
        }
        if ($DB->get_manager()->table_exists(self::LOG)) {
            // Their rows go; the rows other people received from them stay, with the sender removed.
            $DB->delete_records(self::LOG, ['userid' => $uid]);
            $DB->set_field_select(self::LOG, 'sender_userid', 0, 'sender_userid = :uid', ['uid' => $uid]);
        }
        if ($DB->get_manager()->table_exists('local_sentientia_email_prefs')) {
            $DB->delete_records('local_sentientia_email_prefs', ['userid' => $uid]);
        }
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = [];
        if ($DB->get_manager()->table_exists(self::LOG)) {
            $userids = array_merge($userids,
                (array) $DB->get_fieldset_select(self::LOG,
                    'DISTINCT userid', 'userid > 0'));
            $userids = array_merge($userids,
                (array) $DB->get_fieldset_select(self::LOG,
                    'DISTINCT sender_userid', 'sender_userid > 0'));
        }
        if ($DB->get_manager()->table_exists('local_sentientia_email_prefs')) {
            $userids = array_merge($userids,
                (array) $DB->get_fieldset_select('local_sentientia_email_prefs',
                    'DISTINCT userid', 'userid > 0'));
        }
        foreach ([self::OVERRIDES, self::RULES] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $userids = array_merge($userids,
                    (array) $DB->get_fieldset_select($table, 'DISTINCT usermodified', 'usermodified > 0'));
            }
        }
        $userids = array_unique($userids);
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        if ($DB->get_manager()->table_exists(self::LOG)) {
            $DB->delete_records_select(self::LOG, "userid $insql", $inparams);
            [$sendersql, $senderparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'sid');
            $DB->set_field_select(self::LOG, 'sender_userid', 0, "sender_userid $sendersql", $senderparams);
        }
        if ($DB->get_manager()->table_exists('local_sentientia_email_prefs')) {
            $DB->delete_records_select('local_sentientia_email_prefs',
                "userid $insql", $inparams);
        }
        [$editorsql, $editorparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'eid');
        foreach ([self::OVERRIDES, self::RULES] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $DB->set_field_select($table, 'usermodified', 0, "usermodified $editorsql", $editorparams);
            }
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}

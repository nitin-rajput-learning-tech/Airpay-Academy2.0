<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The notifications importer (ADR-032, mapping doc section 11): the BizLMS e-mail
 * history becomes rows of local_sentientia_email_log.
 *
 * Sources: local_emaillogs (the queue and sent log) and, only if it exists,
 * local_email_logs (written by two dead helpers). Both are MAP steps into the same
 * target: there is no id to keep, because nothing outside the log holds one.
 * local_notification_info, _type and _strings are configuration: they are read in
 * place to name the notification type and to recognise the messages that carried
 * account credentials, and are declined as sources.
 *
 * Hard rules, pinned by tests/bizlms_import_test.php:
 *  - Nothing is ever sent or queued. The importer returns outcomes; the writer
 *    inserts log rows; this class calls no mail, message or event API (the static
 *    scan of classes/bizlms/ fails the build if it does).
 *  - Credentials are redacted (see redactor): a message of the users module keeps
 *    its recipient, type, status and timestamps, and loses its subject and body. So does
 *    a message whose template or notification type BizLMS has since deleted, except that
 *    its subject is masked only when it names a secret or account word (COMMS-N1).
 *  - A copy BizLMS sent to a manager (teammemberid > 0) is imported without its body, and
 *    the team member's name leaves its subject, because the member is not carried and so
 *    an erasure of that person could never reach the body (COMMS-N2).
 *  - courseid comes from the courseid column when there is one, else from moduleid for a
 *    template of module type 'course' (the production table has no courseid; COMMS-N3).
 *  - A queue row BizLMS never delivered is imported as not_sent, never failed or
 *    suppressed, so it does not light the dashboard's failure tile and nothing
 *    picks it up to send.
 *  - The deleted-recipient note ("marked sent without delivery") is only for a recipient who
 *    was already deleted when the send ran; one deleted since was delivered to and imports as
 *    plain sent (log_step::delivered_to_deleted_recipient()).
 *  - template_key and rule_id stay NULL, so no reminder dedupe, cap or completion
 *    stamp ever sees an imported row.
 *
 * Depends on nothing: the tenant is the INT root of the recipient's open_path, not
 * a path in the organisation tree, so the org importer need not have run.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements \local_sentientia_platform\bizlms\importer {

    /** Plugin version that carries legacy_source, sender_userid, timesent and body_html. */
    public const REQUIRES_VERSION = 2026093001;

    /** The table the rows land in. */
    private const TARGET = 'local_sentientia_email_log';

    /**
     * @return string
     */
    public function feature(): string {
        return 'notifications';
    }

    /**
     * @return string
     */
    public function component(): string {
        return 'local_sentientia_emails';
    }

    /**
     * @return int
     */
    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * @return string[]
     */
    public function depends(): array {
        return [];
    }

    /**
     * Both tables are optional: a BizLMS database always has local_emaillogs, but a database that has neither has
     * nothing to import.
     *
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            // courseid and time_created are the columns the sender task reads that no install file declares;
            // moduleid and teammemberid are declared by classroom's install.php, and optional here so that a
            // snapshot without them still imports (a row then has no course link and is no manager copy).
            'local_emaillogs' => new source_spec('local_emaillogs', false, [],
                ['courseid', 'time_created', 'moduleid', 'teammemberid']),
            'local_email_logs' => new source_spec('local_email_logs', false, [],
                ['notification_infoid', 'courseid', 'created_date', 'time_created']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [
            'local_notification_info' => 'configuration: the BizLMS templates; read in place to name the type and to '
                . 'recognise messages that carried credentials, never imported',
            'local_notification_type' => 'configuration: the BizLMS notification types; read in place',
            'local_notification_strings' => 'configuration: the placeholder list; nothing reads it',
        ];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [self::TARGET];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * The log's tenant_id is an INT root, not a path, so the generic tenant verify (which checks normalised
     * paths) does not apply; verify() checks the root itself.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            // The recipient has no user row: nobody can see, export or erase the message. Stays in the legacy table.
            new reason('orphan_user', false, true),
            // Only with the decision tenant.unresolved.notifications = skip.
            new reason('tenant_unresolved', false, true),
        ];
    }

    /**
     * Every choice is the owner's (decisions file); none has a default.
     *
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision('notifications.import_bodies', 'Import the e-mail bodies, credentials redacted', true, null,
                [true, false]),
            new decision('notifications.queue_status', 'Status of a queue row BizLMS never delivered', true, null,
                ['not_sent']),
            new decision('notifications.keep_sender', 'Keep who queued the message (sender_userid)', true, null,
                [true, false]),
            new decision('notifications.retention', 'Retention of imported e-mail rows', true, null,
                ['keep_no_purge']),
            new decision('notifications.deleted_recipient_sent',
                'Status of a row BizLMS marked sent for a recipient who was already deleted when BizLMS ran the send',
                true, null, ['sent_with_note', 'suppressed']),
            new decision('tenant.unresolved.notifications', 'A row whose tenant cannot be resolved', true, null,
                ['pathless', 'skip']),
            new decision('notifications.team_member_copy_body',
                'The body of a copy BizLMS sent to a manager (teammemberid > 0): withheld, or imported like any other',
                true, null, ['withhold', 'import']),
            new decision('notifications.course_link',
                'Where an imported e-mail takes its course from when local_emaillogs has no courseid column',
                true, null, ['moduleid_for_course_templates', 'courseid_column_only']),
        ];
    }

    /**
     * @return bool
     */
    public function atomic(): bool {
        return false;
    }

    /**
     * @return array
     */
    public function steps(): array {
        return [new emaillogs_step(), new email_logs_step()];
    }

    /**
     * Read-only. Reports what the import will meet, so the operator sees it before --apply.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $legacy = $ctx->legacy;

        if ($legacy->exists('local_emaillogs')) {
            // Every query below names columns of the legacy tables, and this runs even when the runner has already
            // blocked a missing column, so each one is guarded: a malformed table reports its block, it does not throw.
            if ($legacy->has_column('local_emaillogs', 'status')) {
                $pf->histogram('local_emaillogs.status', [
                    'sent (1)' => $legacy->count('local_emaillogs', ['t.status = 1', []]),
                    'queued (0)' => $legacy->count('local_emaillogs', ['t.status = 0', []]),
                    'null' => $legacy->count('local_emaillogs', ['t.status IS NULL', []]),
                    'other' => $legacy->count('local_emaillogs', ['t.status IS NOT NULL AND t.status NOT IN (0, 1)', []]),
                ]);
            }
            if ($legacy->has_column('local_emaillogs', 'to_userid')) {
                $this->warn_count($pf, 'orphan_recipients:local_emaillogs', (int) $DB->count_records_sql(
                    'SELECT COUNT(1) FROM {local_emaillogs} t LEFT JOIN {user} u ON u.id = t.to_userid WHERE u.id IS NULL'));
                if ($legacy->has_column('local_emaillogs', 'status') && $legacy->has_column('local_emaillogs', 'sent_date')) {
                    // Only a recipient who was already deleted when the send ran is a row BizLMS marked sent without
                    // delivery (log_step::delivered_to_deleted_recipient() compares the same facts); one deleted since
                    // was delivered to. A row whose send date or deletion date is missing cannot be told apart: the
                    // importer keeps the note on it, and says so.
                    $this->warn_count($pf, 'sent_to_deleted_recipient:local_emaillogs', (int) $DB->count_records_sql(
                        'SELECT COUNT(1) FROM {local_emaillogs} t JOIN {user} u ON u.id = t.to_userid '
                        . 'WHERE u.deleted = 1 AND t.status = 1 AND t.sent_date > 0 AND u.timemodified > 0 '
                        . 'AND u.timemodified <= t.sent_date AND u.lastaccess <= t.sent_date'));
                    $this->warn_count($pf, 'sent_to_deleted_recipient_time_unknown:local_emaillogs', (int) $DB->count_records_sql(
                        'SELECT COUNT(1) FROM {local_emaillogs} t JOIN {user} u ON u.id = t.to_userid '
                        . 'WHERE u.deleted = 1 AND t.status = 1 AND (t.sent_date IS NULL OR t.sent_date <= 0 OR u.timemodified <= 0)'));
                }
            }
            if ($legacy->exists('local_notification_info') && $legacy->has_column('local_emaillogs', 'notification_infoid')) {
                $this->warn_count($pf, 'template_not_found:local_emaillogs', (int) $DB->count_records_sql(
                    'SELECT COUNT(1) FROM {local_emaillogs} t '
                    . 'LEFT JOIN {local_notification_info} ni ON ni.id = t.notification_infoid WHERE ni.id IS NULL'));
                if ($legacy->exists('local_notification_type') && $legacy->has_column('local_notification_info', 'notificationid')
                        && $legacy->has_column('local_notification_type', 'pluginname')) {
                    // The messages whose subject and body will be withheld because their type is the users module.
                    // Others are added at import time (a template with the password placeholder, an unresolvable
                    // template that reads like an account message), so this is a lower bound.
                    $this->warn_count($pf, 'credential_rows_users_type:local_emaillogs', (int) $DB->count_records_sql(
                        'SELECT COUNT(1) FROM {local_emaillogs} t '
                        . 'JOIN {local_notification_info} ni ON ni.id = t.notification_infoid '
                        . 'JOIN {local_notification_type} nt ON nt.id = ni.notificationid '
                        . 'WHERE LOWER(nt.pluginname) = :users', ['users' => 'users']));
                }
            }
        }

        if ($legacy->exists('local_emaillogs')) {
            $this->preflight_unresolved_secret_rows($pf, $legacy, 'local_emaillogs', 'emailbody');
            $this->preflight_deleted_recipient_stamps($pf, $legacy);
            $this->preflight_manager_copies($pf, $legacy);
        }

        if ($legacy->exists('local_email_logs')) {
            $this->preflight_unresolved_secret_rows($pf, $legacy, 'local_email_logs', 'body_html');
        }
        if ($legacy->exists('local_email_logs') && $legacy->has_column('local_email_logs', 'to_userid')) {
            $this->warn_count($pf, 'orphan_recipients:local_email_logs', (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM {local_email_logs} t LEFT JOIN {user} u ON u.id = t.to_userid WHERE u.id IS NULL'));
        }

        // The open_path format of the templates, for the legacy_bridge tenant filter (mapping doc, code fix 6):
        // the filter matches /N and /N/..., so a value without its leading slash would not be matched.
        if ($legacy->exists('local_notification_info') && $legacy->has_column('local_notification_info', 'open_path')) {
            $this->warn_count($pf, 'template_open_path_without_leading_slash', (int) $DB->count_records_select(
                'local_notification_info',
                "open_path IS NOT NULL AND open_path <> '' AND " . $DB->sql_like('open_path', ':slash', true, true, true),
                ['slash' => '/%']));
            $this->warn_count($pf, 'template_open_path_empty', (int) $DB->count_records_select(
                'local_notification_info', "open_path IS NULL OR open_path = ''"));
        }
        return $pf;
    }

    /**
     * Read-only, after load. Everything here is a fact the importer promises about what it wrote.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $imported = ['s' => log_step::SOURCE_LABEL];
        $where = 'legacy_source = :s';

        // No imported row may look like something the reminder engine sent.
        if ($DB->count_records_select(self::TARGET, $where . ' AND (template_key IS NOT NULL OR rule_id IS NOT NULL)', $imported)) {
            $failures[] = 'imported_row_carries_a_template_key_or_rule';
        }

        // Only the three statuses the importer writes.
        [$insql, $inparams] = $DB->get_in_or_equal(['sent', 'not_sent', 'suppressed'], SQL_PARAMS_NAMED, 'blmst', false);
        if ($DB->count_records_select(self::TARGET, $where . ' AND status ' . $insql, $imported + $inparams)) {
            $failures[] = 'imported_row_with_an_unexpected_status';
        }

        // The password placeholder and a withheld subject with a body are both credential leaks.
        $like = $DB->sql_like('body_html', ':placeholder', false);
        if ($DB->count_records_select(self::TARGET, $where . ' AND ' . $like,
                $imported + ['placeholder' => '%' . $DB->sql_like_escape(redactor::PASSWORD_PLACEHOLDER) . '%'])) {
            $failures[] = 'imported_body_carries_the_password_placeholder';
        }
        if ($DB->count_records_select(self::TARGET, $where . ' AND subject = :mask AND body_html IS NOT NULL',
                $imported + ['mask' => redactor::SUBJECT_MASK])) {
            $failures[] = 'withheld_subject_with_a_body';
        }

        // The owner's choices hold.
        if (!$ctx->decision('notifications.import_bodies')
                && $DB->count_records_select(self::TARGET, $where . ' AND body_html IS NOT NULL', $imported)) {
            $failures[] = 'body_imported_although_the_decision_says_not_to';
        }
        if (!$ctx->decision('notifications.keep_sender')
                && $DB->count_records_select(self::TARGET, $where . ' AND sender_userid IS NOT NULL', $imported)) {
            $failures[] = 'sender_imported_although_the_decision_says_not_to';
        }
        if ($ctx->decision('notifications.team_member_copy_body') === 'withhold'
                && $this->manager_copies_with_a_body($ctx) > 0) {
            // COMMS-N2: the body of a copy sent to a manager names the team member, and the member is not carried.
            $failures[] = 'manager_copy_imported_with_a_body_although_the_decision_says_to_withhold';
        }

        // COMMS-N1: scrub() is idempotent, so a text that scrub() would still change holds a secret the import let
        // through (or was not run through scrub() at all).
        if ($this->imported_text_with_unredacted_secret()) {
            $failures[] = 'imported_text_with_unredacted_secret';
        }

        // tenant_id is 0 (no tenant) or a registered root. The generic tenant verify checks paths, not roots.
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT tenant_id FROM {' . self::TARGET . '} WHERE ' . $where, $imported) as $root) {
            if ((int) $root === 0) {
                continue;
            }
            try {
                \local_sentientia_platform\tenant::assert_valid((int) $root);
            } catch (\Throwable $e) {
                $failures[] = 'invalid_tenant_value:' . self::TARGET . '.tenant_id=' . (int) $root;
            }
        }
        return $failures;
    }

    /**
     * Nothing to do: no PRESERVE id to reset, no file to copy, no cache to purge.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
    }

    /**
     * Imported rows that are a copy BizLMS sent to a manager (the source row has teammemberid > 0) and still carry a body.
     *
     * @param context $ctx
     * @return int
     */
    private function manager_copies_with_a_body(context $ctx): int {
        global $DB;
        if (!$ctx->legacy->exists('local_emaillogs') || !$ctx->legacy->has_column('local_emaillogs', 'teammemberid')) {
            return 0;
        }
        return (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::TARGET . '} l '
            . 'JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = l.id '
            . 'AND m.sourcetable = :st AND m.subkey = :sk '
            . 'JOIN {local_emaillogs} t ON t.id = m.sourceid '
            . 'WHERE l.legacy_source = :s AND l.body_html IS NOT NULL AND t.teammemberid > 0',
            ['tt' => self::TARGET, 'st' => 'local_emaillogs', 'sk' => '', 's' => log_step::SOURCE_LABEL]);
    }

    /**
     * Does any imported subject or body still hold a secret that redactor::scrub() would blank?
     *
     * Reads a keyset page at a time (the static scan of classes/bizlms/ bans recordsets), so memory stays one page
     * however large the log is. A subject of exactly 255 characters is not checked: it may have been cut inside a mask
     * by the column limit, and the cut mask would then read as a secret.
     *
     * @return bool
     */
    private function imported_text_with_unredacted_secret(): bool {
        global $DB;
        $after = 0;
        do {
            $rows = $DB->get_records_select(self::TARGET, 'legacy_source = :s AND id > :after',
                ['s' => log_step::SOURCE_LABEL, 'after' => $after], 'id ASC', 'id, subject, body_html', 0, 500);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $texts = [];
                if (\core_text::strlen((string) $row->subject) < 255) {
                    $texts[] = (string) $row->subject;
                }
                if ($row->body_html !== null) {
                    $texts[] = (string) $row->body_html;
                }
                foreach ($texts as $text) {
                    $clean = redactor::scrub($text);
                    if ($clean === null || $clean !== redactor::utf8($text)) {
                        return true;
                    }
                }
            }
        } while (count($rows) === 500);
        return false;
    }

    /**
     * Rows whose template or notification type cannot be resolved and whose text names a secret word (COMMS-N1).
     *
     * Their body is withheld whatever it says. Rows with no template reference at all (notification_infoid 0 or NULL: a
     * custom mail, an ILT reminder) are counted too: they are rows whose template cannot be resolved, and the rule
     * withholds their body as well (log_step::credential_reason()). The count is an upper bound of the messages that
     * would have copied a credential into the second table had the rule not been there (the LIKE also matches "spin"
     * and "option"): the number to read on the live backup, where a deleted welcome template is the case that matters.
     *
     * @param preflight $pf
     * @param legacy_reader $legacy
     * @param string $table local_emaillogs or local_email_logs.
     * @param string $bodycolumn The column that holds the body in that table.
     * @return void
     */
    private function preflight_unresolved_secret_rows(preflight $pf, legacy_reader $legacy, string $table,
            string $bodycolumn): void {
        global $DB;
        foreach (['notification_infoid', 'subject', $bodycolumn] as $column) {
            if (!$legacy->has_column($table, $column)) {
                return;
            }
        }
        $joins = '';
        $gone = '1 = 1';
        if ($legacy->exists('local_notification_info') && $legacy->has_column('local_notification_info', 'notificationid')
                && $legacy->exists('local_notification_type')) {
            // A reference of 0 or NULL joins no template, so ni.id is NULL: counted as unresolved, as the importer does.
            $joins = 'LEFT JOIN {local_notification_info} ni ON ni.id = t.notification_infoid '
                . 'LEFT JOIN {local_notification_type} nt ON nt.id = ni.notificationid ';
            $gone = '(ni.id IS NULL OR nt.id IS NULL)';
        }
        $likes = [];
        $params = [];
        foreach (redactor::MENTION_LIKE as $i => $word) {
            $pattern = '%' . $DB->sql_like_escape($word) . '%';
            $likes[] = $DB->sql_like('t.subject', ':blmsub' . $i, false);
            $likes[] = $DB->sql_like('t.' . $bodycolumn, ':blmbody' . $i, false);
            $params['blmsub' . $i] = $pattern;
            $params['blmbody' . $i] = $pattern;
        }
        $this->warn_count($pf, 'unresolved_template_rows_naming_a_secret_word:' . $table, (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . $table . '} t ' . $joins
            . 'WHERE ' . $gone . ' AND (' . implode(' OR ', $likes) . ')', $params));
    }

    /**
     * Two signs that something rewrote the rows of deleted users, which would make an undelivered message look
     * delivered (F-67). The "recipient was already deleted when the send ran" rule compares the user's timemodified
     * with the row's sent date, and anything that updates deleted users' rows (the DPDP anonymiser, an HRMS re-sync, a
     * clean-up) moves that stamp. Run this feature before any such step; a warning here says it may be too late.
     *
     *  - many deleted recipients sharing one timemodified (25 or more, or a quarter of them once there are five)
     *  - deleted recipients whose timemodified is later than the newest sent date of the table
     *
     * @param preflight $pf
     * @param legacy_reader $legacy
     * @return void
     */
    private function preflight_deleted_recipient_stamps(preflight $pf, legacy_reader $legacy): void {
        global $DB;
        foreach (['to_userid', 'status', 'sent_date'] as $column) {
            if (!$legacy->has_column('local_emaillogs', $column)) {
                return;
            }
        }
        $from = 'FROM {local_emaillogs} t JOIN {user} u ON u.id = t.to_userid WHERE u.deleted = 1 AND t.status = 1';
        $deleted = (int) $DB->count_records_sql('SELECT COUNT(DISTINCT u.id) ' . $from);
        if ($deleted === 0) {
            return;
        }
        $top = $DB->get_records_sql(
            'SELECT u.timemodified AS stamp, COUNT(DISTINCT u.id) AS people ' . $from
            . ' GROUP BY u.timemodified ORDER BY people DESC', [], 0, 1);
        $people = $top ? (int) reset($top)->people : 0;
        if ($people >= 25 || ($people >= 5 && $people * 4 >= $deleted)) {
            $pf->warn('many_deleted_recipients_share_one_timemodified:local_emaillogs:' . $people . 'of' . $deleted);
        }
        $newest = (int) $DB->get_field_sql('SELECT MAX(t.sent_date) FROM {local_emaillogs} t WHERE t.status = 1');
        if ($newest > 0) {
            $this->warn_count($pf, 'deleted_recipients_modified_after_the_newest_send:local_emaillogs', (int) $DB->count_records_sql(
                'SELECT COUNT(DISTINCT u.id) ' . $from . ' AND u.timemodified > :newest', ['newest' => $newest]));
        }
    }

    /**
     * How many rows are copies BizLMS sent to a manager (teammemberid > 0): they import without their body (COMMS-N2).
     *
     * @param preflight $pf
     * @param legacy_reader $legacy
     * @return void
     */
    private function preflight_manager_copies(preflight $pf, legacy_reader $legacy): void {
        global $DB;
        if (!$legacy->has_column('local_emaillogs', 'teammemberid')) {
            return;
        }
        $this->warn_count($pf, 'manager_copies:local_emaillogs', (int) $DB->count_records_select(
            'local_emaillogs', 'teammemberid > 0'));
    }

    /**
     * Record a count as a warning when it is not zero.
     *
     * @param preflight $pf
     * @param string $code
     * @param int $count
     * @return void
     */
    private function warn_count(preflight $pf, string $code, int $count): void {
        if ($count > 0) {
            $pf->warn($code . ':' . $count);
        }
    }
}

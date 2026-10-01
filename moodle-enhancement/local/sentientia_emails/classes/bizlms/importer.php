<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
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
 *    its recipient, type, status and timestamps, and loses its subject and body.
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
            'local_emaillogs' => new source_spec('local_emaillogs', false, [], ['courseid', 'time_created']),
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

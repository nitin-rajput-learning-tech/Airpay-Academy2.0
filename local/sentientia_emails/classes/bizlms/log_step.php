<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * What the two notification steps share: one BizLMS e-mail log row becomes one
 * local_sentientia_email_log row (ADR-032, mapping doc section 11).
 *
 * A subclass reads its own table and hands candidate() the row; everything that
 * decides what the imported row looks like lives here, so local_emaillogs and
 * local_email_logs cannot drift apart.
 *
 * Rules, all from the mapping doc and the signed decisions:
 *
 *  - NOTHING is sent. The step returns outcomes; the writer inserts log rows. No
 *    message, no e-mail, no event, and never delivery_log::log(), which under
 *    $CFG->noemailever would rewrite the status to suppressed.
 *  - template_key and rule_id stay NULL. The reminder dedupe, the cap and the
 *    completion stamps all key on template_key, so an imported row can never count
 *    as a reminder the new engine sent.
 *  - status: a row BizLMS delivered is sent; anything else (0, NULL, any other
 *    value) is not_sent, which nothing ever sends and no dashboard counts as a
 *    failure. A row BizLMS marked sent for a recipient who had ALREADY been deleted
 *    when the send ran was never delivered (BZ notification.php:85-88 marks it sent
 *    without sending); it stays sent with a note (or suppressed, per decision). A
 *    recipient deleted AFTER the send was delivered to: that row is plain sent. The
 *    two are told apart by the user row (deleted, timemodified, lastaccess against
 *    the row's sent date), see delivered_to_deleted_recipient().
 *  - tenant_id is the root of the RECIPIENT's current open_path, as for native
 *    rows; only when the recipient has NO path is the template's path tried, and
 *    then 0. The template path never comes first: BizLMS matched templates with an
 *    unbounded LIKE, and it never replaces a path that is there but does not parse.
 *  - credentials: see redactor. A credential row keeps its recipient, type, status
 *    and timestamps and loses its subject and body.
 *  - source timestamps are kept; nothing is stamped with the time of the import.
 *
 * Each step holds a small cache of template facts across its batches. The cache
 * only remembers what the legacy tables said, so a dry run and an apply run read
 * the same answers.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class log_step extends step {

    /** The table every row lands in. */
    public const TARGET = 'local_sentientia_email_log';

    /** legacy_source of an imported row. */
    public const SOURCE_LABEL = 'bizlms';

    /** error_message of a row BizLMS never delivered. */
    public const NOTE_NOT_SENT = 'BizLMS queue: not delivered before cutover';

    /** error_message of a row BizLMS marked sent for a deleted recipient. */
    public const NOTE_DELETED_RECIPIENT = 'BizLMS marked a deleted recipient sent without delivery';

    /** Largest value an INT(10) column is sure to hold; a larger timestamp is garbage. */
    private const MAX_INT = 2147483647;

    /** @var array<int, array|null> Notification template facts by local_notification_info.id; null = not found. */
    private array $templates = [];

    /** @var array<int, bool> Whether a tenant root is registered, by root. */
    private array $roots = [];

    /** @var array<int, \stdClass> Deleted recipients of the current batch: user id => deleted, timemodified, lastaccess. */
    private array $deletedusers = [];

    /**
     * Read one source row into the shape every step maps from.
     *
     * @param \stdClass $row
     * @return array{recipient: int, sender: int, courseid: int, subject: string, body: string, delivered: bool,
     *     sentdate: int, created: int[], prefersent: bool, infoid: int, moduletype: string}
     *     created lists the row's own creation timestamps in priority order; prefersent says that a delivered row
     *     takes its sent date as its creation time (local_emaillogs does).
     */
    abstract protected function candidate(\stdClass $row): array;

    /**
     * @return string
     */
    final public function targettable(): string {
        return self::TARGET;
    }

    /**
     * An epoch second that an INT(10) column can hold; anything else is unknown (0).
     *
     * @param mixed $value
     * @return int
     */
    final protected static function epoch(mixed $value): int {
        $value = (int) $value;
        return ($value > 0 && $value <= self::MAX_INT) ? $value : 0;
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $candidates = [];
        foreach ($rows as $row) {
            $candidates[(int) $row->id] = $this->candidate($row);
        }
        $this->load_templates(array_column($candidates, 'infoid'), $ctx);
        $this->load_deleted_recipients($candidates, $ctx);

        $settings = [
            'bodies' => (bool) $ctx->decision('notifications.import_bodies'),
            'sender' => (bool) $ctx->decision('notifications.keep_sender'),
            'deleted' => (string) $ctx->decision('notifications.deleted_recipient_sent'),
            'unresolved' => (string) $ctx->decision('tenant.unresolved.notifications'),
        ];

        $out = [];
        foreach ($candidates as $id => $candidate) {
            $out[] = $this->map_one($id, $candidate, $settings, $ctx);
        }
        return $out;
    }

    /**
     * Turn one candidate into an outcome.
     *
     * @param int $id Source row id.
     * @param array $c The candidate.
     * @param array{bodies: bool, sender: bool, deleted: string, unresolved: string} $settings
     * @param context $ctx
     * @return outcome
     */
    private function map_one(int $id, array $c, array $settings, context $ctx): outcome {
        // A recipient with no user row cannot be shown, exported or erased: the row stays in the legacy table.
        if ($c['recipient'] <= 0 || !$ctx->lookups->user_exists($c['recipient'])) {
            return outcome::skip($id, 'orphan_user', 'user_not_found');
        }
        $template = $this->templates[$c['infoid']] ?? null;

        [$tenantid, $method] = $this->tenant_of($c['recipient'], $template['open_path'] ?? null, $ctx);
        if ($tenantid === 0 && $settings['unresolved'] === 'skip') {
            return outcome::skip($id, 'tenant_unresolved', 'tenant_not_resolved');
        }

        // Status. The deleted-recipient note is for a recipient who was ALREADY deleted when BizLMS ran the send:
        // that is the only case in which BizLMS marked the row sent without sending (BZ notification.php:85-88).
        // One deleted since was delivered to, and its row is plain sent.
        $warnings = [];
        if ($c['delivered']) {
            $status = 'sent';
            $error = null;
            $deleted = $this->delivered_to_deleted_recipient($c, $ctx);
            if ($deleted !== 'no') {
                $status = $settings['deleted'] === 'suppressed' ? 'suppressed' : 'sent';
                $error = self::NOTE_DELETED_RECIPIENT;
                if ($deleted === 'unknown') {
                    // No send date (or no deletion date) to compare: the note is kept, and the report says so.
                    $warnings[] = 'deleted_recipient_time_unknown';
                }
            }
        } else {
            $status = 'not_sent';
            $error = self::NOTE_NOT_SENT;
        }

        // Subject and body, with credentials redacted.
        $why = $this->credential_reason($c, $template);
        if ($why !== null) {
            $subject = redactor::SUBJECT_MASK;
            $body = null;
            $warnings[] = 'credentials_withheld:' . $why;
        } else {
            $subject = $this->scrubbed($c['subject'], $warnings);
            if ($subject === null) {
                // A subject that could not be scrubbed is masked, and a masked subject never has a body beside it
                // (verify() fails withheld_subject_with_a_body, and the rows are already committed by then).
                $subject = redactor::SUBJECT_MASK;
                $body = null;
                $warnings[] = 'credentials_withheld:subject_scrub_failed';
            } else {
                $body = $settings['bodies'] ? $this->scrubbed($c['body'], $warnings) : null;
                if ($body === '') {
                    $body = null;
                }
            }
        }
        $subject = $ctx->text->fit($subject, 255, 'subject');

        [$timecreated, $timesent, $timewarning] = $this->times($c);
        if ($timewarning !== null) {
            $warnings[] = $timewarning;
        }

        $shortname = $template['shortname'] ?? null;
        $fields = (object) [
            'rule_id' => null,
            'legacy_type' => ($shortname !== null && $shortname !== '') ? $ctx->text->fit($shortname, 100, 'legacy_type') : null,
            'userid' => $c['recipient'],
            'courseid' => ($c['courseid'] > 0 && $ctx->lookups->course_exists($c['courseid'])) ? $c['courseid'] : null,
            'tenant_id' => $tenantid,
            'channel' => 'email',
            'subject' => $subject,
            'template_key' => null,
            'status' => $status,
            'error_message' => $error,
            'attachment_filename' => null,
            'certificate_issue_id' => null,
            'timecreated' => $timecreated,
            'legacy_source' => self::SOURCE_LABEL,
            'sender_userid' => ($settings['sender'] && $c['sender'] > 0 && $ctx->lookups->user_exists($c['sender']))
                ? $c['sender'] : null,
            'timesent' => $timesent,
            'body_html' => $body,
        ];

        $outcome = outcome::insert($id, self::TARGET, $fields)->tenant_method($method);
        foreach ($warnings as $warning) {
            $outcome->warn($warning);
        }
        return $outcome;
    }

    /**
     * Why this row must not keep its subject and body, or null when it may.
     *
     * @param array $c The candidate.
     * @param array|null $template Facts about its template, null when the template is gone.
     * @return string|null users_module, template_placeholder, row_placeholder or unresolved_template.
     */
    private function credential_reason(array $c, ?array $template): ?string {
        if ($template !== null) {
            if (redactor::is_users_module($template['pluginname'], $template['shortname'], $template['moduletype'])) {
                return 'users_module';
            }
            if ($template['password']) {
                return 'template_placeholder';
            }
        }
        if (redactor::is_users_module(null, null, $c['moduletype'])) {
            return 'users_module';
        }
        // The row's own text still carries the unfilled placeholder: whatever the template says (it may be gone or
        // edited since), this message must not keep a subject or body that verify() would then reject.
        if (redactor::template_uses_password($c['subject'], $c['body'])) {
            return 'row_placeholder';
        }
        if ($template === null && redactor::subject_suggests_credentials($c['subject'])) {
            return 'unresolved_template';
        }
        return null;
    }

    /**
     * Was the recipient of a row BizLMS delivered already deleted when the send ran?
     *
     * BizLMS marks a row sent WITHOUT sending when the recipient is deleted at the time of the send
     * (BZ notification.php:85-88). A recipient who is deleted NOW may have been deleted long after a real delivery,
     * so the user row is compared with the row's sent date: Moodle stamps timemodified when it deletes a user, and a
     * user who logged in after the send (lastaccess) was not deleted at that time. Measured on the April 2026
     * production copy: 342 delivered rows belong to users who are deleted now, and for all but about one the user
     * was deleted months after the send.
     *
     * @param array $c The candidate (a delivered row).
     * @param context $ctx
     * @return string 'no' (an active recipient, or one deleted after the send), 'yes' (deleted when the send ran) or
     *         'unknown' (the send date or the deletion date is missing, so the two cannot be compared).
     */
    private function delivered_to_deleted_recipient(array $c, context $ctx): string {
        if ($ctx->lookups->user_active($c['recipient'])) {
            return 'no';
        }
        $user = $this->deletedusers[$c['recipient']] ?? null;
        $sent = $c['sentdate'];
        $modified = $user !== null ? self::epoch($user->timemodified ?? 0) : 0;
        if ($sent <= 0 || $modified <= 0) {
            return 'unknown';
        }
        if ($modified > $sent) {
            return 'no';
        }
        if (self::epoch($user->lastaccess ?? 0) > $sent) {
            return 'no';
        }
        return 'yes';
    }

    /**
     * Read the user rows of the deleted recipients of a batch (deleted, timemodified, lastaccess), once per batch.
     *
     * Only a delivered row to a recipient who exists and is not active needs it; the common case reads nothing.
     *
     * @param array<int, array> $candidates
     * @param context $ctx
     * @return void
     */
    private function load_deleted_recipients(array $candidates, context $ctx): void {
        $this->deletedusers = [];
        $need = [];
        foreach ($candidates as $c) {
            $id = (int) $c['recipient'];
            if ($c['delivered'] && $id > 0 && $ctx->lookups->user_exists($id) && !$ctx->lookups->user_active($id)) {
                $need[$id] = $id;
            }
        }
        if ($need) {
            $this->deletedusers = $ctx->legacy->fetch('user', array_values($need),
                ['id', 'deleted', 'timemodified', 'lastaccess']);
        }
    }

    /**
     * Scrub a subject or body; record what happened.
     *
     * @param string $text
     * @param string[] $warnings Appended to.
     * @return string|null The safe text, or null when the scrub could not run (withhold it).
     */
    private function scrubbed(string $text, array &$warnings): ?string {
        $clean = redactor::scrub($text);
        if ($clean === null) {
            $warnings[] = 'credential_scrub_failed';
            return null;
        }
        if ($clean !== redactor::utf8($text)) {
            $warnings[] = 'credential_text_scrubbed';
        }
        return $clean;
    }

    /**
     * The creation time, the delivery time and a warning when the creation time had to be derived.
     *
     * @param array $c The candidate.
     * @return array{0: int, 1: int|null, 2: string|null} [timecreated, timesent, warning code]
     */
    private function times(array $c): array {
        $sent = $c['sentdate'];
        $warning = null;
        if ($c['prefersent'] && $c['delivered'] && $sent > 0) {
            $created = $sent;
        } else {
            $created = 0;
            foreach ($c['created'] as $position => $time) {
                if ($time > 0) {
                    $created = $time;
                    if ($position > 0) {
                        $warning = 'derived_timestamp';
                    }
                    break;
                }
            }
            if ($created === 0 && $sent > 0) {
                $created = $sent;
                $warning = 'derived_timestamp';
            }
            if ($created === 0) {
                $warning = 'no_source_timestamp';
            }
        }
        return [$created, $sent > 0 ? $sent : null, $warning];
    }

    /**
     * The tenant root of a recipient; only when the recipient has no usable path, the root of the template;
     * else none.
     *
     * The root is an INT (the log's tenant_id), not a path, so this needs no organisation tree: it only
     * normalises the stored open_path and asks the tenant registry whether the root is one.
     *
     * The template path is a fallback and nothing more (mapping doc section 11, "Tenant rule"): BizLMS matched
     * templates with an unbounded LIKE, so it says little about who the recipient belongs to. It is tried only
     * when the recipient has NO path at all (none stored, or an empty one). A recipient whose path is there but
     * does not parse, or parses to a root that is not a registered tenant, is NOT attributed to the template's
     * tenant: the template's path can name another tenant, and filing the message there would put it under
     * somebody else's tenant (ADR-031), so it stays unresolved (0).
     *
     * @param int $userid
     * @param string|null $templatepath local_notification_info.open_path
     * @param context $ctx
     * @return array{0: int, 1: string} [root or 0, method for the report]
     */
    private function tenant_of(int $userid, ?string $templatepath, context $ctx): array {
        $raw = $ctx->lookups->user_path($userid);
        if ($raw !== null && trim($raw) !== '') {
            $path = tenant_resolver::normalise($raw);
            if ($path === null) {
                return [0, 'unresolved'];
            }
            $root = (int) explode('/', ltrim($path, '/'))[0];
            if (!$this->root_is_registered($root)) {
                return [0, 'unresolved'];
            }
            return [$root, $raw === $path ? 'exact' : 'normalised'];
        }

        if ($templatepath !== null && trim($templatepath) !== '') {
            $fallback = tenant_resolver::normalise($templatepath);
            if ($fallback !== null) {
                $root = (int) explode('/', ltrim($fallback, '/'))[0];
                if ($this->root_is_registered($root)) {
                    return [$root, 'fallback:template'];
                }
            }
        }
        return [0, 'unresolved'];
    }

    /**
     * @param int $root
     * @return bool The tenant registry knows this root.
     */
    private function root_is_registered(int $root): bool {
        if (!isset($this->roots[$root])) {
            try {
                \local_sentientia_platform\tenant::assert_valid($root);
                $this->roots[$root] = true;
            } catch (\Throwable $e) {
                $this->roots[$root] = false;
            }
        }
        return $this->roots[$root];
    }

    /**
     * Load the template facts of the notification_info ids a batch needs, once per id.
     *
     * The notification tables are configuration that the import reads in place; it never writes them. A
     * template that BizLMS has since deleted is simply not found.
     *
     * @param int[] $infoids
     * @param context $ctx
     * @return void
     */
    private function load_templates(array $infoids, context $ctx): void {
        $need = [];
        foreach ($infoids as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, $this->templates)) {
                $need[$id] = $id;
            }
        }
        if (!$need) {
            return;
        }
        foreach ($need as $id) {
            $this->templates[$id] = null;
        }
        if (!$ctx->legacy->exists('local_notification_info')) {
            return;
        }
        $infos = $ctx->legacy->fetch('local_notification_info', array_values($need),
            ['id', 'notificationid', 'open_path', 'moduletype', 'subject', 'body']);
        $typeids = [];
        foreach ($infos as $info) {
            $typeids[] = (int) ($info->notificationid ?? 0);
        }
        $types = ($typeids && $ctx->legacy->exists('local_notification_type'))
            ? $ctx->legacy->fetch('local_notification_type', $typeids, ['id', 'shortname', 'pluginname'])
            : [];
        foreach ($infos as $id => $info) {
            $type = $types[(int) ($info->notificationid ?? 0)] ?? null;
            $this->templates[(int) $id] = [
                'shortname' => $type !== null ? (string) ($type->shortname ?? '') : null,
                'pluginname' => $type !== null ? (string) ($type->pluginname ?? '') : null,
                'open_path' => isset($info->open_path) ? (string) $info->open_path : null,
                'moduletype' => (string) ($info->moduletype ?? ''),
                'password' => redactor::template_uses_password(
                    (string) ($info->subject ?? ''), (string) ($info->body ?? '')),
            ];
        }
    }
}

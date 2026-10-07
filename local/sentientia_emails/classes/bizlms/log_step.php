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
 *    and timestamps and loses its subject and body. A row whose template or type cannot be resolved (gone, or never
 *    referenced: notification_infoid 0 or NULL) loses its body whatever it says (COMMS-N1); its subject is masked only
 *    when it names a secret or account word.
 *  - a copy BizLMS sent to a manager (teammemberid > 0) keeps its recipient, type, status and timestamps and
 *    loses its body, and the team member's name leaves the subject (COMMS-N2, decision
 *    notifications.team_member_copy_body = withhold).
 *  - courseid: the column when it is there and the course exists; else, for a template of module type 'course',
 *    the row's moduleid when that course exists (COMMS-N3, decision notifications.course_link).
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

    /** @var array<int, \stdClass> Team members of the manager copies of the current batch: user id => firstname, lastname. */
    private array $teammembers = [];

    /** The placeholder that takes a team member's name out of the subject of a manager copy. */
    public const TEAM_MEMBER_PLACEHOLDER = '[team member]';

    /**
     * Read one source row into the shape every step maps from.
     *
     * @param \stdClass $row
     * @return array{recipient: int, sender: int, courseid: int, subject: string, body: string, delivered: bool,
     *     sentdate: int, created: int[], prefersent: bool, infoid: int, moduletype: string, moduleid: int,
     *     teammember: int}
     *     created lists the row's own creation timestamps in priority order; prefersent says that a delivered row
     *     takes its sent date as its creation time (local_emaillogs does). moduleid is the row's moduleid as a
     *     number (0 when it is not one) and teammember the member a manager copy is about (0 when it is no copy).
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
            'teamcopy' => (string) $ctx->decision('notifications.team_member_copy_body'),
            'courselink' => (string) $ctx->decision('notifications.course_link'),
        ];
        $this->load_team_members($candidates, $settings['teamcopy'], $ctx);

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
     * @param array{bodies: bool, sender: bool, deleted: string, unresolved: string, teamcopy: string,
     *     courselink: string} $settings
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
        if ($why === 'unresolved_template') {
            // The template or its type is gone, so what the message was cannot be known: the body is withheld
            // whatever it says, and the subject is masked only when it names a secret or an account word.
            $subject = $this->subject_of_unresolved_template($c['subject'], $warnings);
            $body = null;
            $warnings[] = 'credentials_withheld:' . $why;
        } else if ($why !== null) {
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

        // A copy BizLMS sent to a manager (teammemberid > 0) is about somebody else: its body names the team member,
        // and the member is not carried, so erasing that person could never reach the body (COMMS-N2). The member's
        // own row already holds the same message, and the legacy table keeps the exact original. The member's name
        // goes from the subject too.
        if ($c['teammember'] > 0 && $settings['teamcopy'] === 'withhold') {
            $body = null;
            if ($subject !== redactor::SUBJECT_MASK) {
                $subject = $this->without_member_name($subject, $this->teammembers[$c['teammember']] ?? null);
            }
            $warnings[] = 'team_member_copy_body_withheld';
        }
        $subject = $ctx->text->fit($subject, 255, 'subject');

        [$timecreated, $timesent, $timewarning] = $this->times($c);
        if ($timewarning !== null) {
            $warnings[] = $timewarning;
        }

        [$courseid, $coursewarning] = $this->course_of($c, $template, $settings['courselink'], $ctx);
        if ($coursewarning !== null) {
            $warnings[] = $coursewarning;
        }

        $shortname = $template['shortname'] ?? null;
        $fields = (object) [
            'rule_id' => null,
            'legacy_type' => ($shortname !== null && $shortname !== '') ? $ctx->text->fit($shortname, 100, 'legacy_type') : null,
            'userid' => $c['recipient'],
            'courseid' => $courseid,
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
     * @return string|null users_module, template_placeholder, row_placeholder or unresolved_template (the template or its
     *         type cannot be resolved, a row with no template reference included).
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
        // The row's own moduletype is empty on every April 2026 production row (the BizLMS users writer never sets it),
        // so this signal never fires there; the template and its type are what carry the answer (COMMS-N1).
        if (redactor::is_users_module(null, null, $c['moduletype'])) {
            return 'users_module';
        }
        // The row's own text still carries the unfilled placeholder: whatever the template says (it may be gone or
        // edited since), this message must not keep a subject or body that verify() would then reject.
        if (redactor::template_uses_password($c['subject'], $c['body'])) {
            return 'row_placeholder';
        }
        // BizLMS hard-deletes templates, so a row that points at a template or a type that is gone can have been the
        // welcome message with the plaintext password. Nothing proves it was not: the whole row is treated as one
        // (decision COMMS-N1, option C: "when $template === null or $template['pluginname'] === null"). That covers a
        // row that never pointed at a template too (notification_infoid 0 or NULL: a custom mail, an ILT reminder, every
        // local_email_logs row whose reference is optional): with no template to read its type from, what the message
        // was cannot be known either, and an unscrubbed body would be copied into body_html, email_detail.php and the
        // DPDP export. The legacy table keeps every withheld body (ADR-032 decision 2).
        if ($template === null || $template['pluginname'] === null) {
            return 'unresolved_template';
        }
        return null;
    }

    /**
     * The subject of a row whose body is withheld because its template is gone: masked when it names a secret or an
     * account word, otherwise scrubbed like any other subject (COMMS-N1).
     *
     * @param string $subject
     * @param string[] $warnings Appended to.
     * @return string The masked or scrubbed subject; the mask when the scrub could not run.
     */
    private function subject_of_unresolved_template(string $subject, array &$warnings): string {
        if (redactor::subject_suggests_credentials($subject) || redactor::text_mentions_secret($subject)) {
            return redactor::SUBJECT_MASK;
        }
        $clean = $this->scrubbed($subject, $warnings);
        return $clean ?? redactor::SUBJECT_MASK;
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
     * Read the names of the team members of the manager copies of a batch (firstname, lastname), once per batch.
     *
     * Only needed when the decision withholds the body of a manager copy: the member's name then leaves the subject.
     * A member whose user row is gone has nothing to scrub (the body is withheld all the same).
     *
     * @param array<int, array> $candidates
     * @param string $teamcopy The decision notifications.team_member_copy_body.
     * @param context $ctx
     * @return void
     */
    private function load_team_members(array $candidates, string $teamcopy, context $ctx): void {
        $this->teammembers = [];
        if ($teamcopy !== 'withhold') {
            return;
        }
        $need = [];
        foreach ($candidates as $c) {
            $member = (int) $c['teammember'];
            if ($member > 0) {
                $need[$member] = $member;
            }
        }
        if ($need) {
            $this->teammembers = $ctx->legacy->fetch('user', array_values($need), ['id', 'firstname', 'lastname']);
        }
    }

    /**
     * A subject without the name of the team member it is about.
     *
     * The member's first and last name, and each word of them of two letters or more, are replaced as whole words,
     * case-insensitively, by TEAM_MEMBER_PLACEHOLDER; the longest name goes first so "Priya Singh" is not left as
     * "[team member] Singh".
     *
     * @param string $subject
     * @param \stdClass|null $member The member's user row (firstname, lastname), null when it is gone.
     * @return string
     */
    private function without_member_name(string $subject, ?\stdClass $member): string {
        if ($member === null) {
            return $subject;
        }
        $names = [];
        foreach ([(string) ($member->firstname ?? ''), (string) ($member->lastname ?? '')] as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            foreach (explode(' ', $name) as $word) {
                if (\core_text::strlen($word) >= 2) {
                    $names[] = $word;
                }
            }
        }
        $names = array_unique(array_filter($names, static fn($n) => \core_text::strlen($n) >= 2));
        usort($names, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($names as $name) {
            $replaced = preg_replace('/(?<![\p{L}\p{N}_])' . preg_quote($name, '/') . '(?![\p{L}\p{N}_])/iu',
                self::TEAM_MEMBER_PLACEHOLDER, $subject);
            if ($replaced !== null) {
                $subject = $replaced;
            }
        }
        return $subject;
    }

    /**
     * The course an imported message belongs to (COMMS-N3).
     *
     * 1. The courseid column when it is there, is above zero and the course exists. A custom mail has -1, and a
     *    course BizLMS has since deleted is gone: neither is a link.
     * 2. Otherwise, with the decision notifications.course_link = moduleid_for_course_templates: the production
     *    local_emaillogs has no courseid column at all, and for a template of module type 'course' its moduleid IS the
     *    course id. It is used when it is above 1 (1 is the site) and that course exists.
     * 3. Otherwise none.
     *
     * @param array $c The candidate.
     * @param array|null $template Facts about its template, null when it is gone.
     * @param string $link The decision value.
     * @param context $ctx
     * @return array{0: int|null, 1: string|null} [course id or null, warning code or null]
     */
    private function course_of(array $c, ?array $template, string $link, context $ctx): array {
        if ($c['courseid'] > 0 && $ctx->lookups->course_exists($c['courseid'])) {
            return [$c['courseid'], null];
        }
        if ($link === 'moduleid_for_course_templates' && $template !== null
                && strtolower(trim($template['moduletype'])) === 'course'
                && $c['moduleid'] > 1 && $ctx->lookups->course_exists($c['moduleid'])) {
            return [$c['moduleid'], 'course_from_moduleid'];
        }
        return [null, null];
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
        // timesent is when BizLMS DELIVERED the message (install.xml: "NULL when it never did"). A row it never
        // delivered can still carry a sent_date, and that is not a delivery time (F-63).
        return [$created, ($c['delivered'] && $sent > 0) ? $sent : null, $warning];
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
            // One answer for every importer (F-11): tenant_resolver asks the tenant registry.
            $this->roots[$root] = tenant_resolver::root_is_registered($root);
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

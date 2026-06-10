<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #7 (2026-05-16) — tenant-scoped welcome email with token replacement.
 *
 * Replaces Moodle's stock `setnew_password_and_mail()` (which sends a
 * generic "set your password" email with zero tenant branding). The new
 * mailer:
 *   1. Looks up the user's tenant from open_path
 *   2. Loads a tenant-specific template (admin-configurable per tenant
 *      id, with fallback to the default)
 *   3. Replaces tokens: [employee_name], [employee_email],
 *      [employee_username], [employee_password], [employee_organization]
 *   4. Sends via Moodle's message API so it honours users' message
 *      preferences + queues + delivery channels
 *
 * Token semantics — tokens are substituted CASE-INSENSITIVELY with the
 * actual user values; if a token has no value (empty / null), it falls
 * back to an empty string so the email reads cleanly.
 *
 * @package local_sentientia_users
 */
class welcome_mailer {

    /** Config keys for the admin-configurable templates. */
    public const CFG_DEFAULT_SUBJECT = 'welcome_email_subject';
    public const CFG_DEFAULT_BODY    = 'welcome_email_body';
    /** Tenant overrides are keyed by costcenterid, e.g. 'welcome_email_body_1'. */
    public const CFG_TENANT_SUBJECT_PREFIX = 'welcome_email_subject_';
    public const CFG_TENANT_BODY_PREFIX    = 'welcome_email_body_';

    /** Default subject when admin has not configured one. */
    public const DEFAULT_SUBJECT = 'Welcome to [employee_organization]';

    /** Default plain-text body (FORMAT_PLAIN); tokens auto-substituted. */
    public const DEFAULT_BODY = <<<TEMPLATE
Hi [employee_name],

Welcome to [employee_organization]. Your account has been created.

Username:  [employee_username]
Email:     [employee_email]
Password:  [employee_password]

Please log in at the link below and change your password on first use.

Need help? Email academy@airpay.co.in.

— The [employee_organization] team
TEMPLATE;

    /**
     * Send a welcome email to a newly-created user.
     *
     * @param int    $userid           The new user's id
     * @param string $plain_password   The plaintext password (used for token
     *                                 substitution; never logged elsewhere)
     * @return bool  True on send-attempt success; false on error (logged via debugging())
     */
    public static function send(int $userid, string $plain_password): bool {
        global $DB, $CFG;
        require_once($CFG->libdir . '/messagelib.php');

        try {
            $user = $DB->get_record('user',
                ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

            $tenantid = self::derive_tenant_id($user->open_path ?? '');
            $org_name = self::derive_org_name($tenantid);

            $tokens = [
                'employee_name'         => trim($user->firstname . ' ' . $user->lastname),
                'employee_email'        => (string) $user->email,
                'employee_username'     => (string) $user->username,
                'employee_password'     => $plain_password,
                'employee_organization' => $org_name,
            ];

            [$subject_template, $body_template] = self::load_templates($tenantid);
            $subject = self::substitute_tokens($subject_template, $tokens);
            $body    = self::substitute_tokens($body_template, $tokens);

            $msg = new \core\message\message();
            $msg->component         = 'local_sentientia_users';
            $msg->name              = 'welcome_email';
            $msg->userfrom          = \core_user::get_noreply_user();
            $msg->userto            = $user;
            $msg->subject           = $subject;
            $msg->fullmessage       = $body;
            $msg->fullmessageformat = FORMAT_PLAIN;
            // HTML version: nl2br + s() on the substituted plain body — the
            // template authors are admins, but defence-in-depth says don't
            // assume their HTML is safe. They get s()-escaped here; if they
            // want HTML, they should send a Wave-3 PR adding format_html mode.
            $msg->fullmessagehtml   = nl2br(s($body));
            $msg->smallmessage      = $subject;
            $msg->notification      = 0;  // direct user mail, not a notification

            return (bool) message_send($msg);
        } catch (\Throwable $e) {
            debugging('local_sentientia_users welcome_mailer failed for user '
                . $userid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Substitute [token_name] occurrences case-insensitively. Missing
     * tokens collapse to empty string (rather than printing the literal
     * placeholder).
     */
    public static function substitute_tokens(string $template, array $tokens): string {
        $patterns = [];
        $replacements = [];
        foreach ($tokens as $key => $val) {
            $patterns[]     = '/\[' . preg_quote($key, '/') . '\]/i';
            $replacements[] = (string) $val;
        }
        return preg_replace($patterns, $replacements, $template);
    }

    /**
     * Load the (subject, body) pair for a given tenant. Tenant overrides
     * win; otherwise fall back to the configured default; otherwise fall
     * back to the in-code DEFAULT_* constants.
     *
     * @return array{0:string,1:string}
     */
    private static function load_templates(int $tenantid): array {
        $tenant_subj = (string) get_config('local_sentientia_users',
            self::CFG_TENANT_SUBJECT_PREFIX . $tenantid);
        $tenant_body = (string) get_config('local_sentientia_users',
            self::CFG_TENANT_BODY_PREFIX . $tenantid);

        $subject = trim($tenant_subj) !== ''
            ? $tenant_subj
            : (string) get_config('local_sentientia_users', self::CFG_DEFAULT_SUBJECT);
        if (trim($subject) === '') {
            $subject = self::DEFAULT_SUBJECT;
        }

        $body = trim($tenant_body) !== ''
            ? $tenant_body
            : (string) get_config('local_sentientia_users', self::CFG_DEFAULT_BODY);
        if (trim($body) === '') {
            $body = self::DEFAULT_BODY;
        }

        return [$subject, $body];
    }

    /**
     * Parse leading numeric segment from an open_path. '/1/3/5' → 1.
     */
    private static function derive_tenant_id(string $open_path): int {
        $parts = explode('/', trim($open_path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    /**
     * Look up the tenant's display name from local_sentientia_org. Falls back
     * to "Airpay Academy" if the tenant is unknown or the org plugin
     * isn't installed.
     */
    private static function derive_org_name(int $tenantid): string {
        if ($tenantid === 0) {
            return 'Airpay Academy';
        }
        global $DB;
        try {
            $name = (string) $DB->get_field('local_sentientia_org', 'name',
                ['id' => $tenantid]);
            return $name !== '' ? $name : 'Airpay Academy';
        } catch (\Throwable $e) {
            return 'Airpay Academy';
        }
    }
}

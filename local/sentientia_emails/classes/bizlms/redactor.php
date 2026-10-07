<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Credential redaction for imported BizLMS e-mails (ADR-032, mapping doc section 11).
 *
 * BizLMS keeps the text it sent. For every notification of the users module the
 * body was built with the new account's PLAINTEXT password: the placeholder
 * [employee_password] is filled from $touser->userpassword, and the substitution
 * also runs on the subject. The queue rows (status 0) still hold it, and so do the
 * rows already sent. A password must never be copied into a second table, so:
 *
 *  1. A row is a CREDENTIAL row when the notification type belongs to the users
 *     module, when its template text uses the password placeholder, when its own
 *     module type says users, or when its template (or its notification type) cannot
 *     be found any more. Templates are edited in place and hard-deleted in BizLMS,
 *     so the template that exists today cannot prove what was sent. The row's own
 *     moduletype column cannot be relied on either: it is empty on every row of the
 *     April 2026 production copy, because the BizLMS users writer never sets it
 *     (decision COMMS-N1, 2026-10-07). A credential row is imported with its body
 *     NULL and its subject MASKED. A row whose template is gone has its body
 *     withheld whatever its text says (it cannot be known what the message was); its
 *     subject is masked only when it names a secret or account word, else scrubbed.
 *     The original stays in the legacy table, which is the archive.
 *  2. Every other subject and body still goes through scrub(), a second line of
 *     defence that blanks the value after words such as password, passcode, PIN,
 *     OTP, secret, token or API key, and the secret parameters of a URL. If the
 *     scrub itself fails, the text is withheld rather than copied. scrub() is
 *     deliberately conservative: it also blanks the bare words "pass" and "pin"
 *     followed by a value, and a word after a secret word that sits behind a tag or a
 *     line break. It changes 0 of the 13,363 kept April rows (F-72), so the cost of
 *     that over-redaction is nil today and it is accepted.
 *  3. importer::verify() runs scrub() over everything that was imported and fails the
 *     run when it would still change a word (imported_text_with_unredacted_secret).
 *     scrub() is idempotent, so a clean import never trips it.
 *
 * Everything here is pure: no database, no Moodle state. It is the part of the
 * importer that can be tested without a site.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class redactor {

    /** The subject of a credential row. Never the original. */
    public const SUBJECT_MASK = '[withheld: account credentials]';

    /** What replaces a scrubbed value. */
    public const MASK = '[redacted]';

    /** The placeholder BizLMS fills from the user's stored plaintext password. */
    public const PASSWORD_PLACEHOLDER = '[employee_password]';

    /** Names of the users module as a notification type's plugin, or a row's module type. */
    private const USERS_NAMES = ['users', 'user', 'local_users'];

    /** Words that introduce a secret value. Matched whole, case-insensitively. */
    private const SECRET_WORDS = 'pass(?:word|wd|code|phrase)?|pwd|otp|pin|secret|token|api[ _-]?key|access[ _-]?key|credentials?';

    /**
     * Words that mean "a secret follows or is named here", for deciding what to do with the SUBJECT of a message
     * whose template is gone (COMMS-N1). No separator is needed: the subject only has to name one. The bare word
     * "pass" is not in the list (it would mask "Exam pass certificate"); password, passwd, passcode and passphrase
     * are. Matched whole, case-insensitively.
     */
    private const MENTION_WORDS = 'pass(?:word|wd|code|phrase)|pwd|otp|pin|secret|token|credentials?';

    /**
     * The same words as plain substrings, for the LIKE of the preflight count (a substring also matches "spin" and
     * "optional", so a count made with it is an upper bound).
     */
    public const MENTION_LIKE = ['password', 'passwd', 'passcode', 'passphrase', 'pwd', 'credential', 'otp', 'pin',
        'secret', 'token'];

    /** A dash as a separator: hyphen, en dash, em dash (the last two as UTF-8 bytes; the patterns are not /u). */
    private const DASH = '-|\xE2\x80[\x93\x94]';

    /**
     * Drop NUL bytes and replace bytes that are not valid UTF-8, so the text is safe to store in a UTF-8 column
     * (the writer refuses anything else, and one bad email must not stop a run).
     *
     * @param string $text
     * @return string
     */
    public static function utf8(string $text): string {
        $text = str_replace("\0", '', $text);
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        return $text;
    }

    /**
     * Does a notification type, a plugin name or a module type name the users module?
     *
     * @param string|null $pluginname local_notification_type.pluginname
     * @param string|null $shortname local_notification_type.shortname
     * @param string|null $moduletype local_emaillogs.moduletype or local_notification_info.moduletype
     * @return bool
     */
    public static function is_users_module(?string $pluginname, ?string $shortname, ?string $moduletype): bool {
        foreach ([$pluginname, $moduletype] as $value) {
            if (in_array(strtolower(trim((string) $value)), self::USERS_NAMES, true)) {
                return true;
            }
        }
        $short = strtolower(trim((string) $shortname));
        return $short === 'users' || preg_match('/^(?:local_)?users?_/', $short) === 1;
    }

    /**
     * Does the template text use the password placeholder?
     *
     * @param string|null $subject
     * @param string|null $body
     * @return bool
     */
    public static function template_uses_password(?string $subject, ?string $body): bool {
        return stripos((string) $subject, self::PASSWORD_PLACEHOLDER) !== false
            || stripos((string) $body, self::PASSWORD_PLACEHOLDER) !== false;
    }

    /**
     * Does a subject read like an account message? Only used when the template is gone and nothing else can
     * say what the message was, so it errs on the side of withholding.
     *
     * "Your Airpay Academy account" is the shape of a real welcome subject (COMMS-N1), so the bare word
     * "account" counts, as do log in, sign in, activation and reset.
     *
     * @param string|null $subject
     * @return bool
     */
    public static function subject_suggests_credentials(?string $subject): bool {
        return preg_match(
            '/\b(?:password|passwd|pwd|passcode|credentials?|log[- ]?in|sign[- ]?in|user ?name|user ?id|welcome'
            . '|account|registration|activat\w*|reset)\b/i',
            (string) $subject) === 1;
    }

    /**
     * Does a text name a secret word (password, passwd, pwd, passcode, credential(s), OTP, PIN, secret, token), with
     * no separator needed? Used for the subject of a message whose template is gone: such a subject is masked when
     * it names one, and scrubbed otherwise. Whole words only, so "spinning" and "optional" do not count.
     *
     * @param string|null $text
     * @return bool
     */
    public static function text_mentions_secret(?string $text): bool {
        return preg_match('/(?<![A-Za-z0-9_])(?:' . self::MENTION_WORDS . ')(?![A-Za-z0-9_])/i', (string) $text) === 1;
    }

    /**
     * Blank the secrets in a text.
     *
     * @param string $text Subject or body (plain text or HTML).
     * @return string|null The text with secrets blanked, valid UTF-8; null when the scrub could not run, in which
     *         case the caller must withhold the text instead of copying it.
     */
    public static function scrub(string $text): ?string {
        $text = self::utf8($text);
        if ($text === '') {
            return '';
        }

        // A gap is whitespace, a non-breaking space entity or a tag (password:</b> abc123). Capped at 50 items:
        // a longer run is not a "password: value" construct, and an unbounded one can exhaust PCRE's JIT stack
        // on a message with a hundred thousand blanks (scrub() would then return null and withhold the text).
        $gap = '(?>(?:\s|&nbsp;|<[^<>]{0,300}>){0,50})';
        // A gap that holds at least one tag or line break, and needs NO separator: the label and the value sit in
        // different table cells, lines or elements ("<td>Password</td><td>x</td>", "Password<br>x"). Whatever token
        // follows it is taken for the value: over-redaction is the safe direction (F-72).
        $taggap = '(?>(?:[ \t]|&nbsp;){0,50}(?:<[^<>]{0,300}>|\r\n|\n|\r)(?:\s|&nbsp;|<[^<>]{0,300}>){0,50})';
        // "password: x", "password = x", "password is x", "password - x", "the password was: x", "password: 'x'".
        // The words is, was and are must end there, or "password island" would read "password is" + "land".
        // The value runs to the next whitespace, '<' or quote, so a ',', ';' or '&' inside a password no longer ends
        // it and leaves its tail behind ("Abc&1234", "Ab;xYz9"). One exception: an '&' that opens the next link
        // parameter ("token=abc&user=5") still ends it, so the rest of the link survives. It has no upper length: a
        // value cut at a limit would leave a tail, and scrub() must be idempotent for importer::verify(). The runs are
        // possessive and made of single character classes, so a very long token costs nothing.
        $value = '((?:[^\s<"\'&]|&(?![A-Za-z_]+=))[^\s<"\'&]*+(?:&(?![A-Za-z_]+=)[^\s<"\'&]*+)*+)';
        $pattern = '/(?<![A-Za-z0-9_])(' . self::SECRET_WORDS . ')(?![A-Za-z0-9_])'
            . '(' . $gap . '(?:(?:(?:is|was|are)(?![A-Za-z0-9_])|:|=|' . self::DASH . ')' . $gap . '){1,3}|' . $taggap . ')'
            . '(["\']?)' . $value . '/i';
        $scrubbed = preg_replace($pattern, '${1}${2}${3}' . self::MASK, $text);
        if ($scrubbed === null) {
            return null;
        }

        // Secret parameters of a link: ?token=..., &amp;password=..., ;key=...
        $scrubbed = preg_replace(
            '/([?&;](?:token|wstoken|access_token|api_?key|key|password|passwd|pwd|secret|otp|auth|code|sesskey)=)[^&\s"\'<>]+/i',
            '${1}' . self::MASK, $scrubbed);
        if ($scrubbed === null) {
            return null;
        }

        // A bearer token in an Authorization header pasted into a message.
        $scrubbed = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]{16,}/i', 'Bearer ' . self::MASK, $scrubbed);
        return $scrubbed;
    }
}

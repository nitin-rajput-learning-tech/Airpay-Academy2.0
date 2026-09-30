<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * The one rule for the meeting and recording links stored on a classroom session.
 *
 * session_manager applies it when a person saves a session; the BizLMS classroom importer
 * (ADR-032, classroom code fix 13) applies the same rule to the links BizLMS stored, so an
 * imported link is exactly as safe as a typed one. It lives in a class of its own because the
 * importer's static scan bans every call on session_manager.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class url_rule {

    /** Length of the meeting_url and recording_url columns. */
    public const MAX_LENGTH = 1024;

    /**
     * Minimal URL sanitiser for session_meeting_url and session_recording_url.
     *
     * Accepts only http(s) URLs with a host. Anything else is rejected: pasting a
     * javascript: or data: URI silently fails so the column never stores a click-through XSS
     * payload.
     *
     * @param string|null $url
     * @return string|null The trimmed URL (cut to the column length), or null for empty or invalid input.
     */
    public static function sanitize(?string $url): ?string {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        return mb_substr($url, 0, self::MAX_LENGTH);
    }

    /**
     * The same rule for a link BizLMS stored.
     *
     * BizLMS accepted any text in these fields, and trainers often pasted a bare www. address.
     * Such a value gets https:// in front, then goes through sanitize(). Nothing else is
     * rewritten: ftp://, javascript: and relative links are refused.
     *
     * @param string|null $url
     * @return string|null
     */
    public static function sanitize_legacy(?string $url): ?string {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if (stripos($url, 'www.') === 0) {
            $url = 'https://' . $url;
        }
        return self::sanitize($url);
    }
}

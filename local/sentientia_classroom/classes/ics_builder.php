<?php
/**
 * Phase H.1 (2026-05-08) — RFC 5545 (iCalendar) builder for classroom sessions.
 *
 * Generates a single VEVENT for one session, suitable for download as a
 * .ics file. Outlook / Google / Apple Calendar all import the file in
 * one click.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

class ics_builder {

    /**
     * Build the full iCalendar payload for one session.
     *
     * @param object $session   row from local_sentientia_classroom_sessions
     * @param object $classroom row from local_sentientia_classroom
     * @param string $organizer_email  Email shown as ORGANIZER (caller's choice)
     * @return string  CRLF-separated iCal text, ready to write to .ics
     */
    public static function build_session(object $session,
                                          object $classroom,
                                          string $organizer_email = 'noreply@airpay.academy'): string {
        global $CFG;

        $now_utc = gmdate('Ymd\THis\Z', time());
        $start_utc = gmdate('Ymd\THis\Z', (int) $session->starttime);
        $end_utc = gmdate('Ymd\THis\Z',
            max((int) $session->starttime + 60, (int) $session->endtime));

        $title = trim((string) ($session->title ?? '')) !== ''
            ? $session->title
            : ((string) $classroom->name . ' — Session');

        // Location: prefer session-level then classroom-level fallback.
        $loc = !empty($session->location) ? (string) $session->location
            : (string) ($classroom->location ?? '');

        // Description: classroom name + notes + a link back to Moodle.
        $description_lines = [
            'Classroom: ' . trim((string) $classroom->name),
        ];
        if (!empty($session->notes)) {
            $description_lines[] = trim((string) $session->notes);
        }
        $description_lines[] = 'View in Moodle: '
            . $CFG->wwwroot . '/local/sentientia_classroom/index.php?id='
            . (int) $classroom->id;
        $description = implode("\\n", $description_lines);

        // ─── Build VEVENT ────────────────────────────────────────────
        // RFC 5545 line-folding @ 75 chars is needed for long lines.
        $uid = 'airpay-classroom-session-' . (int) $session->id . '@airpay.academy';

        // White-label: ORGANIZER common-name is the configured site name,
        // RFC 5545-quoted (DQUOTE-wrap; strip any embedded DQUOTE — RFC 5545
        // provides no escape for it inside a quoted param value). PRODID
        // identifies the generating product (Sentientia), not the customer.
        $organizer_cn = '"' . str_replace('"', '', (string) get_site()->fullname) . '"';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sentientia LMS//Sentientia Classroom//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . self::escape_text($uid),
            'DTSTAMP:' . $now_utc,
            'DTSTART:' . $start_utc,
            'DTEND:' . $end_utc,
            'SUMMARY:' . self::escape_text($title),
            'DESCRIPTION:' . self::escape_text($description),
        ];
        if ($loc !== '') {
            $lines[] = 'LOCATION:' . self::escape_text($loc);
        }
        $lines[] = 'ORGANIZER;CN=' . $organizer_cn . ':mailto:' . $organizer_email;
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 mandates CRLF line endings.
        return implode("\r\n", array_map([self::class, 'fold_line'], $lines))
            . "\r\n";
    }

    /**
     * Escape special chars in an iCal text value.
     * Backslash, comma, semicolon, newline.
     */
    private static function escape_text(string $s): string {
        $s = str_replace(["\\", ";", ",", "\r\n", "\n"],
            ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
        return $s;
    }

    /**
     * Fold lines longer than 75 octets (RFC 5545 §3.1) — continuation
     * lines start with a single space. Most calendar clients tolerate
     * unfolded too, but Outlook on Windows is strict.
     */
    private static function fold_line(string $line): string {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $first = true;
        while (strlen($line) > 75) {
            $out .= ($first ? '' : ' ') . substr($line, 0, 75) . "\r\n";
            $line = substr($line, 75);
            $first = false;
        }
        $out .= ' ' . $line;
        return $out;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridge between the airpay_emails sender pipeline and the bundled
 * `tool_certificate` plugin (Moodle's certificate-template engine).
 *
 * Two things this class does:
 *
 *   1. Look up the certificate issue record for a (user, course)
 *      pair, if one exists. Many courses will have NO certificate
 *      configured (e.g. compliance modules, internal exams); for
 *      those we send the completion email without an attachment.
 *
 *   2. Resolve the issue's PDF to a real filesystem path that
 *      `email_to_user($attachment, $attachname)` can read. The
 *      `tool_certificate\template::get_issue_file()` method returns
 *      a `\stored_file` (i.e. an entry in the Moodle filedir); we
 *      copy it to a private temp directory under $CFG->tempdir for
 *      the duration of the email send.
 *
 * Why temp-copy instead of streaming
 * ----------------------------------
 * `email_to_user()` reads attachments from disk. Streaming from the
 * Moodle filedir is possible but requires knowing the deep filedir
 * layout, which can change between Moodle versions. The temp-copy
 * approach is robust across MDL upgrades and lets the sender clean
 * up after `email_to_user()` returns (so we don't litter $tempdir).
 *
 * The temp directory used here is `$CFG->tempdir/airpay_emails/`,
 * created on first use. Files are written with a randomised suffix
 * to avoid collision when multiple emails are sent in the same
 * cron run.
 *
 * @package local_airpay_emails
 */
class certificate_helper {

    /**
     * Find the most recent tool_certificate issue for a user in a
     * specific course. Returns null when no issue exists (i.e. the
     * course doesn't have a certificate template configured).
     *
     * The lookup matches on (userid, courseid) and orders by
     * timecreated DESC — if a user has multiple issues for the
     * same course (rare, but possible if cert template is reissued
     * after expiry), we pick the newest one.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null Issue row or null if none.
     */
    public static function get_issue_for_user_course(int $userid,
                                                      int $courseid): ?\stdClass {
        global $DB;

        // Defensive guard for environments where tool_certificate isn't
        // installed (some custom Moodle builds disable it).
        if (!$DB->get_manager()->table_exists('tool_certificate_issues')) {
            return null;
        }

        $issues = $DB->get_records('tool_certificate_issues', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ], 'timecreated DESC', '*', 0, 1);

        return $issues ? reset($issues) : null;
    }

    /**
     * Generate (or fetch the cached) PDF for a certificate issue and
     * copy it to a temp path the email sender can attach.
     *
     * Returns an array with both the absolute path (for the
     * `email_to_user($attachment)` parameter — which is relative to
     * $CFG->dataroot, NOT absolute, but the docs disagree; we'll
     * use the absolute path and let `email_to_user` resolve it) and
     * the human-friendly display name.
     *
     * Returns null when:
     *   - tool_certificate plugin isn't installed
     *   - the issue record isn't valid
     *   - PDF generation fails
     *
     * @param \stdClass $issue tool_certificate_issues row
     * @return array{abs_path: string, rel_path: string, display_name: string}|null
     */
    public static function materialise_pdf(\stdClass $issue): ?array {
        global $CFG;

        if (!class_exists('\\tool_certificate\\template')) {
            return null;
        }

        try {
            $template = \tool_certificate\template::instance($issue->templateid);
            $storedfile = $template->get_issue_file($issue);
        } catch (\Throwable $e) {
            // Logged by the caller; we just return null.
            debugging('certificate_helper: failed to fetch issue file for '
                . "issueid={$issue->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        if (!$storedfile) {
            return null;
        }

        // Build a private temp dir and write the PDF into it.
        $tmpdir = $CFG->tempdir . '/airpay_emails';
        if (!is_dir($tmpdir)) {
            if (!@mkdir($tmpdir, $CFG->directorypermissions ?? 0755, true)) {
                debugging("certificate_helper: cannot create temp dir $tmpdir",
                    DEBUG_DEVELOPER);
                return null;
            }
        }

        // Randomised suffix: avoids collision when multiple emails send in
        // parallel (e.g. during a cron run that completes many courses).
        $suffix = bin2hex(random_bytes(6));
        $filename = 'certificate-' . $issue->code . '-' . $suffix . '.pdf';
        $abspath  = $tmpdir . '/' . $filename;

        if (!$storedfile->copy_content_to($abspath)) {
            debugging("certificate_helper: copy_content_to failed for issueid={$issue->id}",
                DEBUG_DEVELOPER);
            return null;
        }

        // `email_to_user()` accepts the attachment path as RELATIVE to
        // $CFG->dataroot — that's the historical Moodle API. Returning
        // both forms so the caller picks the right one.
        $reldir  = trim(str_replace($CFG->dataroot, '', $abspath), '/');

        return [
            'abs_path'     => $abspath,
            'rel_path'     => $reldir,
            // Display name shown in the recipient's email client. Drop
            // the random suffix — the recipient doesn't need to see it.
            'display_name' => 'Airpay-certificate-' . $issue->code . '.pdf',
        ];
    }

    /**
     * Best-effort cleanup of a materialised PDF after the email has
     * been dispatched. Safe to call with null (the sender always
     * does so unconditionally to keep the call site simple).
     *
     * @param array|null $materialised Output of materialise_pdf()
     * @return void
     */
    public static function cleanup_materialised(?array $materialised): void {
        if (!$materialised || empty($materialised['abs_path'])) {
            return;
        }
        // suppress errors — if we can't delete (rare race, locked file),
        // the temp dir will be cleaned up by the periodic Moodle cron
        // task that empties $CFG->tempdir.
        @unlink($materialised['abs_path']);
    }
}

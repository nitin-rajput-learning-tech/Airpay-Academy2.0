<?php
/**
 * Event observer for employee lifecycle automation.
 *
 * Handles:
 * 1. Joiner: Auto-enrol in mandatory courses when user is created
 * 2. Transfer: Re-evaluate enrolments when department changes
 * 3. Exit: Archive progress when user is suspended (handled by Moodle core)
 *
 * 2026-08-07 (KeKa JML investigation, work item 9): the joiner heuristic
 * "mandatory = any visible course with enddate > now" is RETIRED. It
 * enrolled every new user into every dated course platform-wide — not
 * tenant-scoped, not opt-in, not flagged. The observer now:
 *   - does nothing unless sentientia.lifecycle.autoenrol.enabled is ON
 *     (default OFF — ships dark);
 *   - defines mandatory as a visible course carrying the configured
 *     mandatory tag (setting mandatory_tag, default "mandatory");
 *   - tenant-scopes: a course whose open_path roots in a different
 *     tenant than the joiner is never touched; pathless tagged courses
 *     count as platform-wide mandatory (the tag is explicit intent).
 * Definition rationale + custom-field upgrade path: ADR-029.
 *
 * @package    local_sentientia_lifecycle
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_lifecycle;

defined('MOODLE_INTERNAL') || die();

class observer {

    /** Platform feature flag gating joiner auto-enrolment. */
    public const FLAG_AUTOENROL = 'sentientia.lifecycle.autoenrol.enabled';

    /**
     * User created event — auto-enrol in mandatory courses.
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB;

        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid]);

        if (!$user || $user->deleted || $user->suspended) {
            return;
        }

        // Don't auto-enrol admin accounts.
        if (is_siteadmin($userid)) {
            return;
        }

        // Feature gate — default OFF. Resolved for the JOINER's tenant
        // (webhook-created users have no $USER context of their own).
        $tenantroot = self::tenant_root($user->open_path ?? '');
        if (!class_exists('\local_sentientia_platform\feature_flags')
                || !\local_sentientia_platform\feature_flags::is_enabled_for_tenant(
                    self::FLAG_AUTOENROL, $tenantroot)) {
            return;
        }

        self::enrol_in_mandatory_courses($user);
    }

    /**
     * User updated event — check if department changed, re-evaluate enrolments.
     */
    public static function user_updated(\core\event\user_updated $event) {
        // Department change detection would require comparing old vs new open_departmentid.
        // Moodle's user_updated event doesn't carry the old values.
        // On production, this would be handled by the HRMS sync plugin
        // which explicitly checks for department changes during sync.
        // For now, this is a placeholder for future implementation.
    }

    /**
     * Enrol a user in all mandatory courses.
     *
     * Mandatory = visible course (not the site course) carrying the
     * configured mandatory tag, whose open_path (when present) roots in
     * the same tenant as the user.
     */
    private static function enrol_in_mandatory_courses(\stdClass $user): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $tagname = trim((string) get_config('local_sentientia_lifecycle', 'mandatory_tag'));
        if ($tagname === '') {
            $tagname = 'mandatory';
        }

        // {tag}.name holds the normalised (lowercased) form of the tag.
        $mandatorycourses = $DB->get_records_sql(
            "SELECT c.*
               FROM {course} c
               JOIN {tag_instance} ti ON ti.itemid = c.id
                    AND ti.itemtype = 'course' AND ti.component = 'core'
               JOIN {tag} t ON t.id = ti.tagid
              WHERE c.visible = 1 AND c.id > 1 AND t.name = :tag",
            ['tag' => \core_text::strtolower($tagname)]);

        if (empty($mandatorycourses)) {
            return;
        }

        $usertenant = self::tenant_root($user->open_path ?? '');

        $enrolplugin = enrol_get_plugin('manual');
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $now = time();

        foreach ($mandatorycourses as $course) {
            // Tenant scope: a tenanted course only matches a same-tenant
            // joiner. Pathless tagged courses are platform-wide mandatory.
            $coursetenant = self::tenant_root($course->open_path ?? '');
            if ($coursetenant > 0 && $coursetenant !== $usertenant) {
                continue;
            }

            // Check if already enrolled.
            if (is_enrolled(\context_course::instance($course->id), $user->id)) {
                continue;
            }

            // Get or create manual enrol instance.
            $enrolinstance = $DB->get_record('enrol', [
                'courseid' => $course->id,
                'enrol' => 'manual',
            ], '*', IGNORE_MISSING);

            if (!$enrolinstance) {
                $enrolid = $enrolplugin->add_instance($course);
                $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            }

            // Enrol as student; enrolment ends at the course enddate when set.
            try {
                $timeend = !empty($course->enddate) ? (int) $course->enddate : 0;
                $enrolplugin->enrol_user($enrolinstance, $user->id, $studentroleid, $now, $timeend);
            } catch (\Exception $e) {
                // Don't block user creation on a single bad course.
                debugging('Lifecycle auto-enrol failed for user ' . $user->id .
                    ' in course ' . $course->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * First numeric segment of a BizLMS open_path ('/1/2/3' → 1), or 0
     * when the path is empty/malformed.
     */
    private static function tenant_root(string $path): int {
        $parts = explode('/', trim($path, '/'));
        $first = $parts[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_airpayux\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-view metadata renderers.
 *
 * Extracted from `core_renderer.php` in Engineering 31 (decomposition
 * pass 6). Five small utility methods used by the course/view.php
 * template chain to fetch banner images, format the summary, decide
 * whether to render the course menu, etc.
 *
 *   courseviewmenu_hidden(): bool
 *     Returns true on /course/view.php exactly — used by the layout
 *     to suppress the main content menu when the page is the
 *     single-activity-mode course landing.
 *
 *   course_bannerimage(): string
 *     URL of the first valid course overview file (banner image).
 *     Falls back to theme-bundled `course_default` image when no
 *     overview file is present. Pure Moodle core API — no BizLMS.
 *
 *   course_summary_data(): string
 *     HTML-formatted course summary, run through external_format_text
 *     for filters + media embedding.
 *
 *   hasrmaincontenthidden(): bool
 *     Mustache-friendly boolean (true iff courseviewmenu_hidden()).
 *     Templates use this to {{#hasrmaincontenthidden}}...{{/}} blocks.
 *
 *   activityurl_get_course(): \moodle_url|null
 *     For courses in `singleactivity` format, returns the URL of
 *     the embedded activity. Returns null for any other format
 *     (implicit null — the original method had no explicit return).
 *
 * @package theme_airpayux
 */
trait course_view {

    /**
     * True when the current request is for /course/view.php exactly
     * (used to suppress the course main-content menu in that view).
     *
     * @return bool
     */
    public function courseviewmenu_hidden() {

        $pageurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            ? 'https' : 'http';
        $pageurl .= '://';
        $pageurl .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $string = strpos($pageurl, '?');
        $newpageurl = $string ? substr($pageurl, 0, $string) : $pageurl;

        $checkingcourseurl = new \moodle_url('/course/view.php');

        $courseviewmenu = false;
        if ($newpageurl == $checkingcourseurl) {
            $courseviewmenu = true;
        }
        return $courseviewmenu;
    }

    /**
     * URL of the course banner image. Returns the first valid
     * overview file, falling back to the theme default.
     *
     * @return string
     */
    public function course_bannerimage() {
        global $COURSE;
        // Use Moodle core API to get course image — no BizLMS dependency.
        $course = new \core_course_list_element($COURSE);
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return \moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(),
                    $file->get_filearea(),
                    null, $file->get_filepath(), $file->get_filename()
                )->out();
            }
        }
        return $this->image_url('course_default', 'theme_airpayux')->out();
    }

    /**
     * Course summary HTML, fully formatted (filters + media embed).
     *
     * @return string
     */
    public function course_summary_data() {
        global $COURSE, $CFG;
        require_once("$CFG->libdir/externallib.php");

        $course  = $COURSE;
        $context = \context_course::instance($course->id, IGNORE_MISSING);

        list($course->summary, $course->summaryformat) =
            external_format_text($course->summary, $course->summaryformat,
                $context->id, 'course', 'summary', null);
        return $course->summary;
    }

    /**
     * Boolean wrapper around courseviewmenu_hidden() for templates.
     *
     * @return bool
     */
    public function hasrmaincontenthidden() {
        return $this->courseviewmenu_hidden() ? true : false;
    }

    /**
     * URL of the first activity in a single-activity-mode course, or
     * null (implicit) for any other course format.
     *
     * Routed through {@see \local_airpay_core\cm_navigation::resolve_url()}
     * (P0 #9 borrow from Moodle 5.2) so a module that defines a custom
     * navigation URL — e.g. SCORM jumping straight to the player with an
     * attempt id — is honoured here too. Vanilla page/label modules with
     * no callback continue to return $cm->url unchanged.
     *
     * @return \moodle_url|null
     */
    public function activityurl_get_course() {
        global $COURSE;
        $courseformat = course_get_format($COURSE);

        if ($COURSE->format == 'singleactivity') {
            $cm = $courseformat->reorder_activities();
            // P0 #9 (Moodle 5.2) — go through resolver so a module callback
            // (mod_xxx_get_navigation_url) can override the launch target.
            // Falls back to $cm->url when no callback exists.
            return \local_airpay_core\cm_navigation::resolve_url($cm);
        }
        return null;
    }
}

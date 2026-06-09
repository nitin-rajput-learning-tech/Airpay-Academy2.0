<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hook callbacks for local_sentientia_platform.
 *
 * Currently:
 *   - inject_mobile_bottom_nav — adds the mobile bottom-nav HTML to the
 *     page footer for logged-in non-guest users. CSS handles visibility
 *     (display: none above $ap-bp-mobile = 590px).
 *
 * @package local_sentientia_platform
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    /**
     * Inject the mobile bottom-nav into every page footer.
     *
     * The nav renders for ALL logged-in users but is CSS-hidden above
     * the manifesto's $ap-bp-mobile breakpoint (590px) — so it only
     * appears on phones. Adding it to every page means there's no
     * layout shift when a learner navigates from a desktop-only
     * surface to a mobile-friendly one.
     *
     * Active-route detection: we look at $PAGE->url to decide which
     * destination should get aria-current="page" + the underline.
     */
    public static function inject_mobile_bottom_nav(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $USER, $CFG, $OUTPUT, $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Don't inject on login / install / upgrade pages where chrome
        // shouldn't appear.
        $pagetype = $PAGE->pagetype ?? '';
        if (strpos($pagetype, 'login') !== false
                || strpos($pagetype, 'admin-index') !== false
                || strpos($pagetype, 'install') !== false) {
            return;
        }

        // Active route detection — drives aria-current="page" + visual
        // active state on the matching nav item.
        $url = $PAGE->url ? $PAGE->url->out_as_local_url(false) : '';
        $is_home       = (strpos($url, '/my/') === 0);
        $is_mylearning = (strpos($url, '/local/sentientia_catalog/mycourses') !== false);
        $is_search     = (strpos($url, '/local/sentientia_catalog/index') !== false
                          || strpos($url, '/local/sentientia_catalog/index.php') !== false);
        $is_me         = (strpos($url, '/user/profile') !== false
                          || strpos($url, '/user/edit') !== false);

        $data = [
            'homeurl'       => (new \moodle_url('/my/dashboard.php'))->out(false),
            'mycoursesurl'  => (new \moodle_url('/local/sentientia_catalog/mycourses.php'))->out(false),
            'searchurl'     => (new \moodle_url('/local/sentientia_catalog/index.php'))->out(false),
            'profileurl'    => (new \moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false),
            'is_home'       => $is_home,
            'is_mylearning' => $is_mylearning,
            'is_search'     => $is_search,
            'is_me'         => $is_me,
        ];

        $hook->add_html(
            $OUTPUT->render_from_template('theme_sentientia/mobile_bottom_nav', $data)
        );
    }
}

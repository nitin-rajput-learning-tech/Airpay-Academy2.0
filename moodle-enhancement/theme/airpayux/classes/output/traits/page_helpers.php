<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_airpayux\output\traits;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Small page-helper renderers — utilities that are too small to live
 * each in their own trait but that share a "page-chrome utility"
 * theme.
 *
 * Extracted from `core_renderer.php` in Engineering 34
 * (decomposition pass 8). Seven small methods, ~85 lines combined,
 * none warranted its own file:
 *
 *   edit_button($url, $method)        — turn editing on/off button
 *   seteditswtich_display($status)    — toggle the new-style edit pill
 *   edit_switch()                     — render the new-style edit pill
 *   navbar()                          — breadcrumb (delegates to a partial)
 *   is_admin_or_manager()             — role check via course_manager
 *   is_siteadmin_only()               — raw is_siteadmin wrapper
 *   loggedin_username()               — ucwords first name (greeting use)
 *
 * Note on the typo
 * ----------------
 * The original method name was `seteditswtich_display` (sic — "swtich").
 * Renaming would require a sweep across every layout PHP file that
 * calls it; for now we preserve the misspelling to keep this trait
 * extraction a no-behaviour-change refactor. A follow-up commit can
 * deprecate the typo'd name and add `set_editswitch_display` as the
 * canonical entry.
 *
 * Dependencies on the using class
 * --------------------------------
 *   - $this->page                    (inherited from \core_renderer)
 *   - $this->enable_edit_switch      (set by seteditswtich_display)
 *   - $this->render_single_button    (inherited)
 *   - $this->render_from_template    (inherited)
 *
 * The `$enable_edit_switch` property is declared in core_renderer.php
 * itself (a private/protected member on the class) — the trait reads
 * and writes it through `$this->`.
 *
 * @package theme_airpayux
 */
trait page_helpers {

    /**
     * Render the legacy "turn editing on/off" button. New-style themes
     * use the edit pill (`edit_switch()`) instead — this method
     * returns early when the theme declares `haseditswitch`.
     *
     * @param moodle_url $url     Target URL (without sesskey/edit params)
     * @param string     $method  HTTP method on the rendered form
     * @return string|null Rendered button or null if suppressed
     */
    public function edit_button(moodle_url $url, string $method = 'post') {

        if ($this->page->theme->haseditswitch) {
            return;
        }
        $url->param('sesskey', sesskey());
        if ($this->page->user_is_editing()) {
            $url->param('edit', 'off');
            $editstring = get_string('turneditingoff');
        } else {
            $url->param('edit', 'on');
            $editstring = get_string('turneditingon');
        }
        $button = new \single_button($url, $editstring, $method,
            ['class' => 'btn btn-primary']);
        return $this->render_single_button($button);
    }

    /**
     * Toggle whether the edit-switch pill is rendered (some layouts
     * suppress it — login page, kiosk pages, etc.).
     *
     * (Method name kept misspelled — see trait class comment.)
     *
     * @param bool $status true to enable, false to suppress
     */
    public function seteditswtich_display($status) {
        $this->enable_edit_switch = $status;
    }

    /**
     * Create a navbar switch for toggling editing mode.
     *
     * @return string Html containing the edit switch
     */
    public function edit_switch() {
        if ($this->page->user_allowed_editing() && $this->enable_edit_switch) {

            $temp = (object) [
                'legacyseturl'  => (new moodle_url('/editmode.php'))->out(false),
                'pagecontextid' => $this->page->context->id,
                'pageurl'       => $this->page->url,
                'sesskey'       => sesskey(),
            ];
            if ($this->page->user_is_editing()) {
                $temp->checked = true;
            }
            return $this->render_from_template('core/editswitch', $temp);
        }
    }

    /**
     * Renders the "breadcrumb" for all pages.
     *
     * The custom airpayux_navbar class is a thin VO that the
     * `core/navbar` mustache template knows how to render.
     *
     * @return string the HTML for the navbar.
     */
    public function navbar(): string {
        $newnav = new \theme_airpayux\airpayux_navbar($this->page);
        return $this->render_from_template('core/navbar', $newnav);
    }

    /**
     * True when the current user has the
     * `local/airpay_courses:manage` capability at the system context.
     * Used by layouts to switch between admin and learner navigation
     * shells.
     *
     * @return bool
     */
    public function is_admin_or_manager() {
        $context = \context_system::instance();
        return \local_airpay_courses\course_manager::can_manage($context);
    }

    /**
     * Bare wrapper around `is_siteadmin()` so templates can call
     * `{{#output.is_siteadmin_only}}…{{/}}` directly (templates can't
     * call top-level functions).
     *
     * @return bool
     */
    public function is_siteadmin_only() {
        return is_siteadmin();
    }

    /**
     * Title-case first name of the currently logged-in user.
     * Used in greetings ("Welcome back, Nitin").
     *
     * @return string
     */
    public function loggedin_username() {
        global $USER;
        return ucwords($USER->firstname);
    }
}

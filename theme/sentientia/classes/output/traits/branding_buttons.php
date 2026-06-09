<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Branding-button + copyright + footer-social-icon helpers.
 *
 * Extracted from `core_renderer.php` (which was 2,339 lines monolithic)
 * in Phase 9.5 engineering item 3. These methods are pure-string-return
 * helpers that read theme settings and render template fragments — no
 * shared state with sibling renderer methods, so they trait cleanly.
 *
 * The trait is consumed by `\theme_sentientia\output\core_renderer` via
 * a `use \theme_sentientia\output\traits\branding_buttons;` declaration.
 *
 * Methods provided:
 *   helpbtn():           string  — help-desc text from theme settings
 *   aboutbtn():          string  — about-us text from theme settings
 *   contactbtn():        string  — contact text from theme settings
 *   get_copyright_text(): string — formatted copyright from theme settings
 *   secure_login_info(): string  — delegates to inherited login_info(false)
 *   footer_social_icons(): string — renders socialicons.mustache
 *
 * All methods assume `$this->page` (Moodle's $PAGE) and
 * `$this->render_from_template()` are available — i.e. they assume the
 * consuming class extends \core_renderer or equivalent.
 *
 * @package theme_sentientia
 */
trait branding_buttons {

    /**
     * Help-desc text from theme settings (empty string if unset).
     */
    public function helpbtn() {
        $helptext = $this->page->theme->settings->helpdesc ?? '';
        return !empty($helptext) ? $helptext : '';
    }

    /**
     * About-us text from theme settings (empty string if unset).
     */
    public function aboutbtn() {
        $aboutustext = $this->page->theme->settings->aboutus ?? '';
        return !empty($aboutustext) ? $aboutustext : '';
    }

    /**
     * Contact text from theme settings (empty string if unset).
     */
    public function contactbtn() {
        $contactustext = $this->page->theme->settings->contact ?? '';
        return !empty($contactustext) ? $contactustext : '';
    }

    /**
     * Copyright string from theme settings, run through format_text() so
     * site administrators can use HTML in the copyright field.
     */
    public function get_copyright_text() {
        return format_text($this->page->theme->settings->copyright ?? '', FORMAT_HTML);
    }

    /**
     * Secure-page variant of login info (no full menu, just the
     * minimum required by Moodle's secure-page contract).
     */
    public function secure_login_info() {
        return $this->login_info(false);
    }

    /**
     * Footer social-network icon row. Reads facebook/twitter/linkedin/
     * youtube/instagram URLs from theme settings; renders the
     * `theme_sentientia/socialicons` template.
     *
     * The template handles the empty case (no networks configured) by
     * rendering nothing.
     */
    public function footer_social_icons() {
        $settings = $this->page->theme->settings;
        $hasfacebook  = !empty($settings->facebook)  ? $settings->facebook  : false;
        $hastwitter   = !empty($settings->twitter)   ? $settings->twitter   : false;
        $haslinkedin  = !empty($settings->linkedin)  ? $settings->linkedin  : false;
        $hasyoutube   = !empty($settings->youtube)   ? $settings->youtube   : false;
        $hasinstagram = !empty($settings->instagram) ? $settings->instagram : false;

        $socialcontext = [
            'hassocialnetworks' => (bool) ($hasfacebook || $hastwitter
                || $haslinkedin || $hasyoutube || $hasinstagram),
            'socialicons' => [
                'facebook'  => $hasfacebook,
                'twitter'   => $hastwitter,
                'linkedin'  => $haslinkedin,
                'youtube'   => $hasyoutube,
                'instagram' => $hasinstagram,
            ],
        ];
        return $this->render_from_template('theme_sentientia/socialicons', $socialcontext);
    }
}

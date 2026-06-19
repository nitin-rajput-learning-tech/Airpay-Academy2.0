<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Login-form renderers.
 *
 * Extracted from `core_renderer.php` in Engineering 28 (decomposition
 * pass 4). Houses the two render_* methods Moodle dispatches to when
 * the user lands on /login/index.php or the OTP login page:
 *
 *   render_login(\core_auth\output\login $form): string
 *   render_otplogin(\core_auth\output\otplogin $form): string
 *
 * Both methods enrich the template context with tenant-specific data
 * pulled via the airpay branding helpers (loginlogo, loginslider —
 * both provided by the `login_ui` trait that core_renderer also uses)
 * and the per-tenant logo URL via the inherited get_logo_url().
 *
 * Why this is a separate trait from `login_ui`
 * --------------------------------------------
 * `login_ui` is the source of UI-fragment helpers (loginlogo,
 * loginslider, login text strings). `login_render` is the
 * orchestration that wires those fragments into the full login-form
 * template. Splitting them keeps each trait focused on one
 * abstraction level — the UI helpers are reusable from other
 * surfaces (e.g. signup) while the render_* methods are
 * Moodle-renderer-protocol-specific.
 *
 * @package theme_sentientia
 */
trait login_render {

    /**
     * Render the login form. Enriches the template context with:
     *
     *   - signup URL (when self-registration is enabled for the tenant)
     *   - cookies help icon (depends on rememberusername setting)
     *   - error text formatted as HTML
     *   - tenant logo URL via get_logo_url()
     *   - site name (HTML-safe formatted)
     *   - theme settings: helpdesc, contact, aboutus
     *
     * @param \core_auth\output\login $form Moodle login renderable
     * @return string Rendered login form HTML
     */
    public function render_login(\core_auth\output\login $form) {
        global $CFG, $SITE, $OUTPUT;

        // Check both new (sentientia_users) and legacy (local_users) config.
        // The legacy path is kept until every tenant migrates to the
        // new plugin's settings; see Phase 6B sprint plan.
        $organization_shortname = get_config('local_sentientia_users', 'organization_shortname')
                               ?: get_config('local_users', 'organization_shortname');
        $activeregistration = get_config('local_sentientia_users', 'activeregistration')
                           ?: get_config('local_users', 'activeregistration');

        $context = $form->export_for_template($this);

        if (trim($organization_shortname != "") && $activeregistration == 1) {
            $context->signupurl_custom = new \moodle_url('/local/sentientia_users/signup.php');
        }

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);

        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl  = $url;
        $context->sitename = format_string($SITE->fullname, true,
            ['context' => \context_course::instance(SITEID), "escape" => false]);
        $context->output   = $OUTPUT;

        // Phase B.3.a (2026-05-23, Moodle 5.2 rebase) — 5.2 boost added
        // `$context->hasauthinstructions = !empty($CFG->auth_instructions);`
        // to render_login() so the login form's "Authentication instructions"
        // block can conditionally render. Mirror it here so a Moodle 5.2
        // template referencing this context key gets the expected boolean.
        // 5.1 templates that don't reference it are unaffected.
        $context->hasauthinstructions = !empty($CFG->auth_instructions);

        $helptext      = $this->page->theme->settings->helpdesc;
        $contactustext = $this->page->theme->settings->contact;
        $aboutustext   = $this->page->theme->settings->aboutus;
        if (!empty($helptext) || !empty($contactustext) || !empty($aboutustext)) {
            $context->helptext      = $helptext;
            $context->contactustext = $contactustext;
            $context->aboutustext   = $aboutustext;
        } else {
            $context->helptext      = '';
            $context->contactustext = '';
            $context->aboutustext   = '';
        }
        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Render the OTP login form. Simpler than render_login — no signup
     * branch (OTP login is reserved for existing users), no theme help
     * text (kept minimal since this is a security-sensitive step).
     *
     * @param object $form Moodle OTP renderable. NOTE: typed loosely on purpose —
     *   \core_auth\output\otplogin existed on Moodle <5.2 but was REMOVED in 5.2
     *   (core no longer dispatches OTP login). A hard type-hint here would be a
     *   "class not found" landmine for static analysers / opcache preload on 5.2.
     * @return string Rendered OTP form HTML
     */
    public function render_otplogin($form) {
        global $CFG, $SITE, $OUTPUT;

        $context = $form->export_for_template($this);

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);

        $context->sitename = format_string($SITE->fullname, true,
            ['context' => \context_course::instance(SITEID), "escape" => false]);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl    = $url;
        $context->output     = $OUTPUT;
        // loginlogo()  → provided by trait \theme_sentientia\output\traits\branding_assets
        // loginslider() → provided by trait \theme_sentientia\output\traits\login_ui
        $context->loginlogo   = $this->loginlogo();
        $context->loginslider = $this->loginslider();

        return $this->render_from_template('core/otploginform', $context);
    }
}

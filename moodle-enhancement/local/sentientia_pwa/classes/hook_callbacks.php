<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_sentientia_pwa.
 *
 * Replaces the legacy function-name callback
 * `local_sentientia_pwa_before_standard_top_of_body_html()` with a proper
 * hook subscription per Moodle 5.2's new hook system.
 *
 * The legacy function in lib.php is kept (delegating to this class) so
 * the same plugin still works on 5.1 deployments that haven't migrated
 * yet. On 5.2 the legacy callback is intentionally a no-op and this
 * class is the canonical entry point.
 *
 * Migration record
 * ----------------
 * Phase B.3 web smoke (2026-05-23) on Moodle 5.2 surfaced this
 * deprecation notice:
 *   "Callback before_standard_top_of_body_html in local_sentientia_pwa
 *    component should be migrated to new hook callback for
 *    core\hook\output\before_standard_top_of_body_html_generation"
 * This class is the migration target.
 *
 * What it does
 * ------------
 * Renders the PWA "Install" CTA banner above the standard top-of-body
 * HTML on the user dashboard. The banner is hidden by default; the
 * `local_sentientia_pwa/install_prompt` AMD module reveals it when
 * Chrome fires `beforeinstallprompt`.
 *
 * Surface filter (same as the legacy implementation):
 *   - $PAGE->pagelayout must be 'mydashboard'
 *   - $PAGE->url path must NOT be inside /local/sentientia_pwa/
 *   - User must NOT have dismissed the CTA in the last 7 days
 *   - Feature flag `sentientia.pwa.install.enabled` must be ON
 *
 * @package    local_sentientia_pwa
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Hook entry point — injects the PWA Install CTA + loads the
     * install_prompt AMD module.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        $html = self::build_install_cta_html();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Build the install-CTA HTML — identical contract to the legacy
     * function in lib.php so the AMD module + template still work
     * unchanged.
     *
     * Zero-cost when the feature flag is off (returns '' before any
     * DB / template work).
     *
     * @return string HTML to inject, or '' when feature is off / surface
     *                doesn't match / user dismissed recently.
     */
    public static function build_install_cta_html(): string {
        global $PAGE, $OUTPUT, $USER;

        // Parent feature flag class must exist (local_airpay_core).
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return '';
        }
        try {
            if (!\local_airpay_core\feature_flags::is_enabled('sentientia.pwa.install.enabled')) {
                return '';
            }
        } catch (\Throwable $e) {
            // Feature-flag table not migrated yet on a fresh install /
            // PHPUnit fixture without local_airpay_core schema. Never
            // block the page on a flag-resolver hiccup.
            return '';
        }

        // 2026-05-22 — tightened to ONLY 'mydashboard'. The CTA was
        // leaking onto admin pages + manage-users pages where it
        // overlapped existing content. Dashboard is the canonical
        // install-prompt surface.
        $layout = $PAGE && $PAGE->pagelayout ? $PAGE->pagelayout : '';
        if ($layout !== 'mydashboard') {
            return '';
        }

        // Skip when the page is itself a PWA endpoint (defensive).
        $urlpath = $PAGE && $PAGE->url ? (string) $PAGE->url->out_omit_querystring() : '';
        if (str_contains($urlpath, '/local/sentientia_pwa/')) {
            return '';
        }

        // 2026-05-22 — honour the user's prior dismissal server-side.
        // Without this gate, the JS-side localStorage check ran AFTER
        // the server rendered the CTA, so the markup was still in the
        // DOM and any race condition could reveal it. Now if the user
        // dismissed within the last 7 days, no CTA HTML is sent at all.
        if (!empty($USER->id) && (int) $USER->id > 1) {
            $dismissedat = (int) get_user_preferences(
                'local_sentientia_pwa_install_dismissed_at', 0, $USER);
            if ($dismissedat > 0
                && (time() - $dismissedat) < (7 * 86400)) {
                return '';
            }
        }

        // Queue the AMD module init for this page.
        $PAGE->requires->js_call_amd('local_sentientia_pwa/install_prompt', 'init');

        // Render the hidden banner — AMD module reveals when
        // beforeinstallprompt fires.
        try {
            return $OUTPUT->render_from_template('local_sentientia_pwa/install_cta', []);
        } catch (\Throwable $e) {
            // Template missing during partial deploy — silent fail.
            return '';
        }
    }
}
